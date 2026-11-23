'use strict';
// NaviSuite desktop client: main process.
//
// The window holds three views:
//   bar    a thin status bar (local page) with the connection indicator
//   app    the existing NaviSuite web interface, loaded from the vessel
//          server over HTTPS. No Node.js, no preload, sandboxed.
//   local  the client's own pages: choosing a server, and "connection lost"
//
// The client has no database and no backend of its own. Everything the crew
// does happens on the vessel server, under each person's own sign-in.

const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const {
  app, BaseWindow, WebContentsView, session, ipcMain, shell, Menu, safeStorage, dialog, powerMonitor,
} = require('electron');

const { ConfigStore, readManaged, defaultManagedPath } = require('./config');
const { ConnectionMonitor, REACHABLE } = require('./connection');
const discovery = require('./discovery');
const { identifyServer, quickHealth } = require('./probe');
const { getJson } = require('./http');
const { verifyServerCertificate, sameFingerprint, displayFingerprint } = require('./trust');
const { httpsOrigin, normaliseHost } = require('./hosts');
const { checkCompatibility } = require('./compat');
const { writeManagedConfig } = require('./config');

const PARTITION = 'persist:navisuite';
const BAR_HEIGHT = 40;
const RENDERER_DIR = path.join(__dirname, '..', 'renderer');
const PRELOAD_DIR = path.join(__dirname, '..', 'preload');
const RENDERER_URL = pathToFileURL(RENDERER_DIR + path.sep).href;
const ALLOWED_PERMISSIONS = new Set(['clipboard-sanitized-write', 'fullscreen']);
const SMOKE = process.env.NAVISUITE_SMOKE === '1';

if (process.env.NAVISUITE_CLIENT_DATA) app.setPath('userData', process.env.NAVISUITE_CLIENT_DATA);
app.setAppUserModelId('com.navisuite.client');

let win = null;
let barView = null;
let localView = null;
let appView = null;
let store = null;
let settings = null; // managed settings merged with defaults
let managedError = null;
let config = null; // the trusted server, or null
let monitor = null;
let shown = null; // 'app' | 'local'
let localPage = null;
let lastAppUrl = null;
let appLoadFailed = false;
let lastRediscovery = 0;
const probes = new Map(); // candidate id -> identified server, kept in the main process only

// ------------------------------------------------------------------ logging

function logLine(level, message) {
  const line = `${new Date().toISOString()} ${level} ${message}\n`;
  try {
    const dir = path.join(app.getPath('userData'), 'logs');
    fs.mkdirSync(dir, { recursive: true });
    const file = path.join(dir, 'client.log');
    try {
      if (fs.statSync(file).size > 1024 * 1024) fs.renameSync(file, `${file}.1`);
    } catch { /* new file */ }
    fs.appendFileSync(file, line);
  } catch { /* logging must never break the client */ }
  if (SMOKE || !app.isPackaged) process.stdout.write(line);
}

function smoke(event, data = {}) {
  if (SMOKE) process.stdout.write(`SMOKE ${JSON.stringify({ event, ...data })}\n`);
}

// ------------------------------------------------------------------ helpers

function serverOrigin(host = config && config.activeHost) {
  return config && host ? httpsOrigin(host, config.httpsPort) : null;
}

/** True for https URLs on one of the configured server's addresses. */
function isServerUrl(url) {
  if (!config) return false;
  try {
    const u = new URL(url);
    const port = u.port === '' ? 443 : Number(u.port);
    return u.protocol === 'https:' && config.hosts.includes(u.hostname.toLowerCase()) && port === config.httpsPort;
  } catch {
    return false;
  }
}

/** Same page, on the address currently in use. */
function appUrl(previous) {
  const origin = serverOrigin();
  if (!origin) return null;
  if (!previous) return `${origin}/`;
  try {
    const u = new URL(previous);
    return `${origin}${u.pathname}${u.search}${u.hash}`;
  } catch {
    return `${origin}/`;
  }
}

function openExternal(url) {
  try {
    const u = new URL(url);
    if (['http:', 'https:', 'mailto:'].includes(u.protocol)) shell.openExternal(u.href);
  } catch { /* ignore malformed links */ }
}

function fromLocalPage(event) {
  const url = event.senderFrame && event.senderFrame.url;
  return typeof url === 'string' && url.startsWith(RENDERER_URL);
}

function serverSummary() {
  if (!config) return null;
  return {
    vesselId: config.vesselId,
    vesselName: config.vesselName,
    host: config.activeHost,
    hosts: config.hosts,
    fingerprint: displayFingerprint(config.caSha256),
    trustedVia: config.trustedVia,
  };
}

// ------------------------------------------------------------------ window and views

function layout() {
  if (!win) return;
  const { width, height } = win.getContentBounds();
  barView.setBounds({ x: 0, y: 0, width, height: BAR_HEIGHT });
  const content = { x: 0, y: BAR_HEIGHT, width, height: Math.max(0, height - BAR_HEIGHT) };
  appView.setBounds(content);
  localView.setBounds(content);
}

function show(which) {
  const add = which === 'app' ? appView : localView;
  const remove = which === 'app' ? localView : appView;
  try { win.contentView.removeChildView(remove); } catch { /* not shown */ }
  win.contentView.addChildView(add);
  shown = which;
  layout();
  sendBar();
}

function showLocal(page, query = {}) {
  localPage = page;
  localView.webContents.loadFile(path.join(RENDERER_DIR, page), { query });
  show('local');
  smoke('local-page', { page, kind: query.kind || null });
}

function showApp(url) {
  if (!config) return;
  const target = url || appUrl(lastAppUrl);
  appLoadFailed = false;
  show('app');
  appView.webContents.loadURL(target).catch(() => { /* reported by did-fail-load */ });
}

/** Back to the application, reloading only if its page failed to load. */
function resumeApp() {
  if (!config) return;
  if (appLoadFailed || !appView.webContents.getURL() || !isServerUrl(appView.webContents.getURL())) {
    showApp(appUrl(lastAppUrl));
    smoke('app-resumed', { reloaded: true });
  } else {
    // The page itself never failed: keep it, with anything typed into it.
    show('app');
    smoke('app-resumed', { reloaded: false });
  }
}

function showSetup(reason) {
  if (settings.lockServer && config) {
    dialog.showMessageBox(win, { type: 'info', message: 'The server is set by your administrator and cannot be changed here.' });
    return;
  }
  showLocal('setup.html', reason ? { reason } : {});
}

function sendBar() {
  if (!barView) return;
  const state = monitor ? monitor.state : { state: config ? 'connecting' : 'setup' };
  barView.webContents.send('bar:state', {
    ...state,
    view: shown,
    server: serverSummary(),
    canGoBack: shown === 'app' && appView.webContents.navigationHistory.canGoBack(),
    canGoForward: shown === 'app' && appView.webContents.navigationHistory.canGoForward(),
    locked: settings.lockServer,
  });
}

/** Local pages may only show local files; links leave for the browser. */
function lockLocalContents(contents) {
  contents.on('will-navigate', (event, url) => {
    if (!url.startsWith(RENDERER_URL)) {
      event.preventDefault();
      openExternal(url);
    }
  });
  contents.setWindowOpenHandler(({ url }) => {
    openExternal(url);
    return { action: 'deny' };
  });
}

/** Rules for the NaviSuite pages and the windows they open (print views). */
function applyAppPolicies(contents) {
  const allowed = (url) => isServerUrl(url) || url === 'about:blank' || url === '';
  contents.on('will-navigate', (event, url) => {
    if (!allowed(url)) {
      event.preventDefault();
      openExternal(url);
    }
  });
  contents.on('will-redirect', (event, url) => {
    if (!allowed(url)) event.preventDefault();
  });
  contents.setWindowOpenHandler(({ url }) => {
    if (allowed(url)) {
      return {
        action: 'allow',
        overrideBrowserWindowOptions: {
          width: 1100,
          height: 800,
          autoHideMenuBar: true,
          webPreferences: { partition: PARTITION, sandbox: true, contextIsolation: true, nodeIntegration: false },
        },
      };
    }
    openExternal(url);
    return { action: 'deny' };
  });
  contents.on('did-create-window', (child) => applyAppPolicies(child.webContents));
}

function setupAppSession() {
  const ses = session.fromPartition(PARTITION);

  // The vessel server's certificate must come from the pinned vessel CA.
  // Other sites (the CDNs some pages still use) get Chromium's normal check.
  ses.setCertificateVerifyProc((request, callback) => {
    const host = String(request.hostname || '').toLowerCase();
    if (config && config.hosts.includes(host)) {
      const result = verifyServerCertificate({ leafPem: request.certificate.data, caPem: config.caPem, host });
      if (!result.ok) logLine('WARN', `Refused certificate for ${host}: ${result.reason}`);
      // -202 is net::ERR_CERT_AUTHORITY_INVALID. A plain -2 would surface as
      // ERR_FAILED and look like a network fault rather than an untrusted server.
      callback(result.ok ? 0 : -202);
      return;
    }
    callback(-3);
  });

  ses.setPermissionRequestHandler((_contents, permission, callback) => callback(ALLOWED_PERMISSIONS.has(permission)));
  ses.setPermissionCheckHandler((_contents, permission) => ALLOWED_PERMISSIONS.has(permission));
  return ses;
}

function createWindow() {
  win = new BaseWindow({
    width: 1366,
    height: 860,
    minWidth: 900,
    minHeight: 600,
    title: 'NaviSuite',
    show: false,
    autoHideMenuBar: true,
    backgroundColor: '#f3f5f8',
  });

  const localPrefs = (preload) => ({
    preload: path.join(PRELOAD_DIR, preload), contextIsolation: true, sandbox: true, nodeIntegration: false, spellcheck: false,
  });
  barView = new WebContentsView({ webPreferences: localPrefs('bar.js') });
  localView = new WebContentsView({ webPreferences: localPrefs('local.js') });
  appView = new WebContentsView({
    webPreferences: { partition: PARTITION, contextIsolation: true, sandbox: true, nodeIntegration: false, spellcheck: false },
  });

  lockLocalContents(barView.webContents);
  lockLocalContents(localView.webContents);
  applyAppPolicies(appView.webContents);

  const wc = appView.webContents;
  wc.on('did-fail-load', (_e, code, description, url, isMainFrame) => {
    if (!isMainFrame || code === -3) return; // -3: navigation replaced by another
    appLoadFailed = true;
    if (url && isServerUrl(url)) lastAppUrl = url;
    logLine('WARN', `Page failed to load (${code} ${description}): ${url}`);
    if (code <= -200 && code > -300) {
      // Certificate errors: never show the page; say why.
      showLocal('offline.html', { kind: 'untrusted' });
    } else {
      showLocal('offline.html');
    }
    if (monitor) monitor.checkNow();
  });
  wc.on('did-navigate', (_e, url) => { if (isServerUrl(url)) lastAppUrl = url; sendBar(); });
  wc.on('did-navigate-in-page', (_e, url) => { if (isServerUrl(url)) lastAppUrl = url; sendBar(); });
  wc.on('did-finish-load', () => {
    if (!appLoadFailed) smoke('app-loaded', { url: wc.getURL(), title: wc.getTitle() });
  });
  wc.on('page-title-updated', (_e, title) => {
    win.setTitle(title ? `${title} - NaviSuite` : 'NaviSuite');
  });
  wc.on('render-process-gone', (_e, details) => {
    logLine('WARN', `Page process ended (${details.reason}); reloading.`);
    if (details.reason !== 'clean-exit') showApp(appUrl(lastAppUrl));
  });

  win.contentView.addChildView(barView);
  win.on('resize', layout);
  win.on('closed', () => {
    win = null;
    app.quit();
  });
  layout();

  barView.webContents.once('did-finish-load', () => {
    win.maximize();
    win.show();
    sendBar();
  });
  barView.webContents.loadFile(path.join(RENDERER_DIR, 'bar.html'));
}

// ------------------------------------------------------------------ connection

async function checkServer() {
  const order = [config.activeHost, ...config.hosts.filter((h) => h !== config.activeHost)];
  let failure = null;
  for (const host of order) {
    try {
      const { json } = await getJson(`${httpsOrigin(host, config.httpsPort)}/health`, { ca: config.caPem, timeoutMs: 4000, maxBytes: 16384 });
      if (!json || json.app !== 'NaviSuite') {
        failure = failure || { state: 'offline', host, detail: 'Something other than NaviSuite answers at this address.' };
        continue;
      }
      if (config.vesselId && json.vessel && json.vessel.id !== config.vesselId) {
        return { state: 'wrong-server', host, detail: `This address now belongs to ${json.vessel.name || `vessel ${json.vessel.id}`}.` };
      }
      const compat = checkCompatibility(json, app.getVersion());
      if (!compat.ok) {
        // Not reachable for this client; checked again with the usual
        // back-off, since the server or the client may be updated meanwhile.
        return { state: 'incompatible', host, detail: compat.message, reason: compat.reason, release: json.release || null };
      }
      if (host !== config.activeHost) {
        logLine('INFO', `Server now reached at ${host}.`);
        config.activeHost = host;
        persistConfig();
      }
      if (json.vessel && json.vessel.name && json.vessel.name !== config.vesselName) {
        config.vesselName = json.vessel.name;
        persistConfig();
      }
      const state = json.status === 'ok' ? 'online' : json.status === 'degraded' ? 'degraded' : 'down';
      const detail = {
        online: 'Connected',
        degraded: 'Connected. The server reports that something needs attention.',
        down: 'The server answers but reports a problem (database or configuration).',
      }[state];
      return { state, host, detail, release: json.release || null };
    } catch (err) {
      if (err.kind === 'tls') {
        failure = { state: 'untrusted', host, detail: err.message };
      } else if (!failure || failure.state !== 'untrusted') {
        failure = { state: 'offline', host, detail: err.message };
      }
    }
  }
  return failure || { state: 'offline', detail: 'No server address.' };
}

function persistConfig() {
  try {
    config = store.save(config);
  } catch (err) {
    logLine('ERROR', `Could not save settings: ${err.message}`);
  }
}

function onState(state) {
  sendBar();
  smoke('state', { state: state.state, host: state.host || null });
  if (state.failures === 1 || state.failures % 10 === 0) logLine('INFO', `Connection ${state.state}: ${state.detail || ''}`);

  if (!REACHABLE.has(state.state)) {
    // One missed check only shows in the bar; two in a row, or a refused
    // certificate, also covers the page so nobody keeps typing into it.
    if (shown === 'app' && (state.failures >= 2 || state.state !== 'offline')) {
      showLocal('offline.html', { kind: state.state });
    } else if (shown === 'local' && localPage === 'offline.html') {
      localView.webContents.send('local:state-changed', state);
    }
    if (state.state === 'offline' && state.unreachableSince && Date.now() - state.unreachableSince > 60000) rediscover();
  } else if (shown === 'local' && localPage === 'offline.html') {
    resumeApp();
  }
}

/**
 * The server stopped answering for a minute: maybe its address changed.
 * Ask the LAN again, and accept a new address only from a server with the
 * same vessel and the same pinned certificate authority.
 */
async function rediscover() {
  if (!settings.discovery.enabled || Date.now() - lastRediscovery < 60000) return;
  lastRediscovery = Date.now();
  try {
    const found = await discovery.discover({ port: settings.discovery.port, vesselId: config.vesselId || undefined, timeoutMs: 2000 });
    const match = found.find((c) => sameFingerprint(c.caSha256, config.caSha256));
    if (!match) return;
    const added = [match.host, ...match.addresses].filter((h) => h && !config.hosts.includes(h));
    if (added.length) {
      config.hosts = [...added, ...config.hosts].slice(0, 8);
      config.activeHost = added[0];
      persistConfig();
      logLine('INFO', `Rediscovered the server at ${added.join(', ')}.`);
    }
    if (monitor) monitor.checkNow();
  } catch (err) {
    logLine('WARN', `Rediscovery failed: ${err.message}`);
  }
}

function startMonitor() {
  if (monitor) monitor.stop();
  monitor = new ConnectionMonitor({ check: checkServer });
  monitor.on('state', onState);
  monitor.on('reconnected', () => logLine('INFO', 'Reconnected.'));
  monitor.start();
}

function useServer(server) {
  config = store.save(server);
  win.setTitle(`NaviSuite - ${config.vesselName || config.activeHost}`);
  lastAppUrl = null;
  startMonitor();
  showApp(`${serverOrigin()}/`);
}

// ------------------------------------------------------------------ choosing a server

function candidateId(c) {
  return `${c.vesselId || 0}|${c.caSha256}`;
}

/** Is the server already trusted by Windows (CA installed by IT)? */
async function trustedByWindows(host, httpsPort) {
  const probeSession = session.fromPartition('navisuite-probe');
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 4000);
  try {
    const res = await probeSession.fetch(`${httpsOrigin(host, httpsPort)}/health`, { signal: controller.signal, redirect: 'error' });
    return res.status === 200 || res.status === 503;
  } catch {
    return false;
  } finally {
    clearTimeout(timer);
  }
}

/** Identify a server and decide how it can be trusted. Kept in main. */
async function identify(host, { httpsPort = 443, httpPort = 80, source, extraHosts = [], announcedFingerprint = null }) {
  const found = await identifyServer(host, { httpsPort, httpPort });
  const compat = checkCompatibility(found.health, app.getVersion());
  if (!compat.ok) throw new Error(compat.message);
  if (announcedFingerprint && !sameFingerprint(announcedFingerprint, found.caSha256)) {
    throw new Error(`The server at ${host} announced one certificate and serves another. Not connecting.`);
  }
  const managed = settings.server;
  if (managed && managed.vesselId && found.vesselId !== managed.vesselId) {
    throw new Error(`This is the server of another vessel (${found.vesselName || found.vesselId}). Your administrator set this PC for a different vessel.`);
  }
  let verifiedBy = null;
  if (managed && managed.caSha256) {
    if (!sameFingerprint(managed.caSha256, found.caSha256)) {
      throw new Error('This server\'s certificate does not match the one your administrator configured. Not connecting.');
    }
    verifiedBy = 'managed';
  } else if (await trustedByWindows(host, httpsPort)) {
    verifiedBy = 'windows';
  }
  const hosts = [...new Set([host, ...extraHosts].filter(Boolean))];
  const entry = { ...found, hosts, source, verifiedBy };
  const id = candidateId(entry);
  const existing = probes.get(id);
  if (existing) entry.hosts = [...new Set([...existing.hosts, ...hosts])];
  probes.set(id, entry);
  return { id, ...publicCandidate(entry) };
}

function publicCandidate(c) {
  return {
    vesselId: c.vesselId,
    vesselName: c.vesselName,
    release: c.release,
    status: c.status,
    hosts: c.hosts,
    fingerprint: displayFingerprint(c.caSha256),
    verifiedBy: c.verifiedBy,
  };
}

async function runDiscovery(sender) {
  const progress = (message) => { if (!sender.isDestroyed()) sender.send('local:progress', message); };
  const results = [];
  const seen = new Set();
  const add = (r) => { if (!seen.has(r.id)) { seen.add(r.id); results.push(r); } };

  if (settings.server) {
    progress('Checking the server set by your administrator...');
    for (const host of settings.server.hosts) {
      try {
        add(await identify(host, { ...settings.server, source: 'managed', extraHosts: settings.server.hosts }));
        break;
      } catch (err) {
        logLine('WARN', `Managed server ${host}: ${err.message}`);
      }
    }
    if (settings.lockServer) return results;
  }

  if (!settings.discovery.enabled) return results;

  progress('Searching the network...');
  const answers = await discovery.discover({ port: settings.discovery.port });
  logLine('INFO', `Discovery: ${answers.length} answer(s).`);
  for (const a of answers) {
    progress(`Checking ${a.vesselName || 'a server'} at ${a.addresses[0]}...`);
    // The IP address the answer came from always works; the host name might
    // not resolve on every PC, so it is kept as a second address.
    for (const address of a.addresses) {
      try {
        add(await identify(address, {
          httpsPort: a.httpsPort, httpPort: a.httpPort, source: 'discovery',
          extraHosts: [a.host, ...a.addresses], announcedFingerprint: a.caSha256,
        }));
        break;
      } catch (err) {
        logLine('WARN', `Discovered server ${address}: ${err.message}`);
      }
    }
  }

  if (results.length === 0 && settings.discovery.sweep) {
    const targets = discovery.sweepTargets();
    progress(`No answer to the broadcast. Asking ${targets.length} addresses on this network...`);
    const hits = await discovery.sweep({
      targets,
      probe: (ip) => quickHealth(ip).then((h) => (h ? ip : null)),
      onProgress: (done, total) => { if (done % 32 === 0) progress(`Asked ${done} of ${total} addresses...`); },
    });
    for (const ip of hits) {
      try {
        add(await identify(ip, { source: 'discovery' }));
      } catch (err) {
        logLine('WARN', `Swept server ${ip}: ${err.message}`);
      }
    }
  }
  return results;
}

function registerIpc() {
  ipcMain.handle('local:get-setup', (event) => {
    if (!fromLocalPage(event)) return null;
    return {
      current: serverSummary(),
      locked: settings.lockServer,
      managedServer: settings.server ? settings.server.hosts : null,
      managedError,
      settingsError: store.lastError,
      discoveryEnabled: settings.discovery.enabled,
      version: app.getVersion(),
    };
  });

  ipcMain.handle('local:discover', async (event) => {
    if (!fromLocalPage(event)) return { error: 'Not allowed.' };
    try {
      return { servers: await runDiscovery(event.sender) };
    } catch (err) {
      return { error: err.message };
    }
  });

  ipcMain.handle('local:probe', async (event, input) => {
    if (!fromLocalPage(event)) return { error: 'Not allowed.' };
    if (settings.lockServer) return { error: 'The server is set by your administrator.' };
    const host = normaliseHost(input);
    if (!host) return { error: 'Enter a computer name or an IP address, for example 192.168.1.10.' };
    try {
      return { server: await identify(host, { source: 'manual' }) };
    } catch (err) {
      return { error: err.message };
    }
  });

  ipcMain.handle('local:connect', async (event, id) => {
    if (!fromLocalPage(event)) return { error: 'Not allowed.' };
    const c = probes.get(id);
    if (!c) return { error: 'Search again; this server is no longer in the list.' };
    if (settings.lockServer && c.source !== 'managed') return { error: 'The server is set by your administrator.' };
    if (settings.server && settings.server.caSha256 && !sameFingerprint(settings.server.caSha256, c.caSha256)) {
      return { error: 'This server does not match the one your administrator configured.' };
    }
    const server = {
      vesselId: c.vesselId,
      vesselName: c.vesselName,
      hosts: c.hosts,
      activeHost: c.hosts[0],
      httpsPort: c.httpsPort,
      httpPort: c.httpPort,
      caPem: c.caPem,
      caSha256: c.caSha256,
      source: c.source,
      trustedVia: c.verifiedBy || 'user',
      confirmedAt: new Date().toISOString(),
    };
    logLine('INFO', `Connecting to ${c.vesselName || 'vessel ' + c.vesselId} at ${c.hosts.join(', ')}, CA ${c.caSha256}, trusted via ${server.trustedVia}.`);
    try {
      useServer(server);
      probes.clear();
      return { ok: true };
    } catch (err) {
      return { error: err.message };
    }
  });

  ipcMain.handle('local:cancel', (event) => {
    if (!fromLocalPage(event) || !config) return false;
    resumeApp();
    return true;
  });

  ipcMain.handle('local:retry', (event) => {
    if (!fromLocalPage(event)) return false;
    if (monitor) monitor.checkNow();
    return true;
  });

  ipcMain.handle('local:open-setup', (event) => {
    if (!fromLocalPage(event)) return false;
    showSetup();
    return true;
  });

  ipcMain.handle('local:state', (event) => (fromLocalPage(event) && monitor ? monitor.state : null));

  ipcMain.on('bar:action', (event, action) => {
    if (!fromLocalPage(event)) return;
    const history = appView.webContents.navigationHistory;
    switch (action) {
      case 'back': if (shown === 'app' && history.canGoBack()) history.goBack(); break;
      case 'forward': if (shown === 'app' && history.canGoForward()) history.goForward(); break;
      case 'reload': reloadApp(); break;
      case 'home': if (config) showApp(`${serverOrigin()}/`); break;
      case 'server': showSetup(); break;
      case 'about': showAbout(); break;
      default: break;
    }
  });

  ipcMain.on('bar:ready', (event) => { if (fromLocalPage(event)) sendBar(); });
}

// ------------------------------------------------------------------ menu

function reloadApp() {
  if (!config) return;
  if (shown === 'app' && !appLoadFailed) appView.webContents.reload();
  else showApp(appUrl(lastAppUrl));
  if (monitor) monitor.checkNow();
}

function zoom(delta) {
  const wc = appView.webContents;
  wc.setZoomLevel(delta === 0 ? 0 : Math.max(-3, Math.min(4, wc.getZoomLevel() + delta)));
}

function showAbout() {
  const s = config;
  const state = monitor ? monitor.state : null;
  dialog.showMessageBox(win, {
    type: 'info',
    title: 'About NaviSuite',
    message: `NaviSuite desktop client ${app.getVersion()}`,
    detail: [
      s ? `Vessel: ${s.vesselName || 'unknown'}${s.vesselId ? ` (id ${s.vesselId})` : ''}` : 'No server chosen yet.',
      s ? `Server: ${s.activeHost} (also: ${s.hosts.filter((h) => h !== s.activeHost).join(', ') || 'none'})` : '',
      state && state.release ? `Server release: ${state.release}` : '',
      s ? `Trusted via: ${{ user: 'confirmed fingerprint', managed: 'administrator settings', windows: 'Windows certificate store' }[s.trustedVia]}` : '',
      s ? `Server fingerprint (SHA-256):\n${displayFingerprint(s.caSha256)}` : '',
      `Electron ${process.versions.electron}, Chromium ${process.versions.chrome}`,
    ].filter(Boolean).join('\n'),
  });
}

function buildMenu() {
  const template = [
    {
      label: '&NaviSuite',
      submenu: [
        { label: 'Change server...', enabled: !settings.lockServer, click: () => showSetup() },
        { label: 'About NaviSuite', click: showAbout },
        { type: 'separator' },
        { role: 'quit' },
      ],
    },
    { label: '&Edit', submenu: [{ role: 'undo' }, { role: 'redo' }, { type: 'separator' }, { role: 'cut' }, { role: 'copy' }, { role: 'paste' }, { role: 'selectAll' }] },
    {
      label: '&View',
      submenu: [
        { label: 'Back', accelerator: 'Alt+Left', click: () => { if (appView.webContents.navigationHistory.canGoBack()) appView.webContents.navigationHistory.goBack(); } },
        { label: 'Forward', accelerator: 'Alt+Right', click: () => { if (appView.webContents.navigationHistory.canGoForward()) appView.webContents.navigationHistory.goForward(); } },
        { label: 'Reload', accelerator: 'F5', click: reloadApp },
        { label: 'Home', accelerator: 'Alt+Home', click: () => { if (config) showApp(`${serverOrigin()}/`); } },
        { label: 'Print...', accelerator: 'CmdOrCtrl+P', click: () => { if (shown === 'app') appView.webContents.print(); } },
        { type: 'separator' },
        { label: 'Zoom in', accelerator: 'CmdOrCtrl+=', click: () => zoom(0.5) },
        { label: 'Zoom out', accelerator: 'CmdOrCtrl+-', click: () => zoom(-0.5) },
        { label: 'Actual size', accelerator: 'CmdOrCtrl+0', click: () => zoom(0) },
        { role: 'togglefullscreen' },
        ...(settings.allowDevTools || !app.isPackaged
          ? [{ type: 'separator' }, { label: 'Developer tools', accelerator: 'F12', click: () => appView.webContents.toggleDevTools() }]
          : []),
      ],
    },
  ];
  Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}

// ------------------------------------------------------------------ start and quit

function loadSettings() {
  try {
    settings = readManaged(defaultManagedPath());
  } catch (err) {
    managedError = err.message;
    logLine('ERROR', `Administrator settings ignored: ${err.message}`);
    settings = readManaged('<none>');
  }
}

let quitting = false;
app.on('before-quit', (event) => {
  if (!settings || !settings.clearSessionOnExit || quitting) return;
  // Crew PCs are shared: the next person must sign in as themselves.
  event.preventDefault();
  quitting = true;
  if (monitor) monitor.stop();
  session.fromPartition(PARTITION)
    .clearStorageData({ storages: ['cookies', 'localstorage', 'indexdb', 'serviceworkers', 'cachestorage'] })
    .catch(() => {})
    .finally(() => app.quit());
});

app.on('web-contents-created', (_e, contents) => {
  contents.on('will-attach-webview', (event) => event.preventDefault());
});

// Checkpoint 8: the installer runs NaviSuite.exe once, elevated, to write
// the administrator settings from its command line (/SERVER=, /FINGERPRINT=,
// /VESSEL=, /LOCK), so they are checked by the same code that reads them.
// Nothing else starts: no window, no single-instance lock, no user data.
const INSTALLER_FLAG = '--navisuite-write-managed-config';
if (process.argv.includes(INSTALLER_FLAG)) {
  const arg = (name) => {
    const found = process.argv.find((a) => a.startsWith(`--${name}=`));
    return found === undefined ? undefined : found.slice(name.length + 3);
  };
  let code = 0;
  try {
    app.setPath('userData', path.join(require('node:os').tmpdir(), 'navisuite-client-installer'));
    const file = arg('file') || defaultManagedPath();
    writeManagedConfig(file, {
      hosts: (arg('server') || '').split(',').map((h) => h.trim()).filter(Boolean),
      caSha256: arg('fingerprint') || null,
      vesselId: arg('vessel') ? Number(arg('vessel')) : null,
      lockServer: process.argv.includes('--lock'),
    });
    process.stdout.write(`Administrator settings written to ${file}\n`);
  } catch (err) {
    process.stderr.write(`${err.message}\n`);
    code = 2;
  }
  app.exit(code);
} else if (!app.requestSingleInstanceLock()) {
  app.quit();
} else {
  app.on('second-instance', () => {
    if (win) {
      if (win.isMinimized()) win.restore();
      win.focus();
    }
  });

  app.whenReady().then(() => {
    loadSettings();
    store = new ConfigStore({ dir: app.getPath('userData'), safeStorage });
    if (!store.canEncrypt) logLine('WARN', 'Windows encryption is not available; settings are stored unencrypted.');
    setupAppSession();
    registerIpc();
    buildMenu();
    createWindow();

    config = store.load();
    if (config && settings.server) {
      const m = settings.server;
      const mismatch = (m.caSha256 && !sameFingerprint(m.caSha256, config.caSha256))
        || (settings.lockServer && !config.hosts.some((h) => m.hosts.includes(h)));
      if (mismatch) {
        logLine('INFO', 'Saved server differs from the administrator settings; choosing again.');
        config = null;
      }
    }
    if (config) {
      win.setTitle(`NaviSuite - ${config.vesselName || config.activeHost}`);
      startMonitor();
      showApp(`${serverOrigin()}/`);
    } else {
      showLocal('setup.html', store.lastError ? { reason: 'settings' } : {});
    }

    powerMonitor.on('resume', () => { if (monitor) monitor.checkNow(); });
  });
}
