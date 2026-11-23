'use strict';
// End-to-end check of the real Electron app against a fake vessel server:
// managed settings -> setup page verifies the server -> app page loads over
// pinned HTTPS -> server goes away -> offline page -> server returns ->
// the app reconnects on its own.
//
//   npm run smoke
//
// Needs a desktop session (it opens a window) and openssl.

const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const electron = require('electron');
const { makeVesselPki } = require('../helpers/pki');
const { startFakeVessel } = require('../helpers/fake-vessel');
const { parseCaCertificate } = require('../../src/main/trust');

const STEP_TIMEOUT_MS = 45000;

async function main() {
  const pki = makeVesselPki();
  const vessel = await startFakeVessel({ pki });
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'navisuite-smoke-'));
  const managedFile = path.join(dir, 'client-config.json');
  fs.writeFileSync(managedFile, JSON.stringify({
    server: { hosts: ['127.0.0.1'], httpsPort: vessel.ports.https, httpPort: vessel.ports.http, vesselId: 12, caSha256: parseCaCertificate(pki.caPem).sha256 },
    lockServer: true,
    discovery: { enabled: false },
  }));

  // Editors built on Electron (VS Code) set ELECTRON_RUN_AS_NODE for their
  // child processes, which would start Electron as plain Node.
  const env = { ...process.env };
  delete env.ELECTRON_RUN_AS_NODE;
  const waiters = [];
  let child = null;
  let stderr = '';
  const launch = () => {
    child = spawn(electron, [path.join(__dirname, '..', '..'), '--use-mock-keychain'], {
      env: { ...env, NAVISUITE_SMOKE: '1', NAVISUITE_CLIENT_DATA: path.join(dir, 'data'), NAVISUITE_MANAGED_CONFIG: managedFile },
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let buffer = '';
    child.stdout.on('data', (chunk) => {
      buffer += chunk;
      let i;
      while ((i = buffer.indexOf('\n')) >= 0) {
        const line = buffer.slice(0, i);
        buffer = buffer.slice(i + 1);
        if (!line.startsWith('SMOKE ')) continue;
        const ev = JSON.parse(line.slice(6));
        console.log('  ', JSON.stringify(ev));
        for (const w of [...waiters]) if (w.match(ev)) { waiters.splice(waiters.indexOf(w), 1); w.resolve(ev); }
      }
    });
    child.stderr.on('data', (c) => { stderr += c; });
  };
  const quit = () => new Promise((resolve) => { child.once('exit', resolve); child.kill(); });
  launch();

  const waitFor = (label, match) => new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error(`Timed out waiting for: ${label}`)), STEP_TIMEOUT_MS);
    waiters.push({ match, resolve: (ev) => { clearTimeout(timer); resolve(ev); } });
  });

  const results = [];
  const step = async (label, fn) => {
    const started = Date.now();
    await fn();
    results.push([label, `${((Date.now() - started) / 1000).toFixed(1)} s`]);
  };

  let passed = false;
  try {
    await step('setup page opens and verifies the managed server', () => waitFor('setup page', (e) => e.event === 'local-page' && e.page === 'setup.html'));
    await step('application page loads over pinned HTTPS', async () => {
      const ev = await waitFor('app page', (e) => e.event === 'app-loaded');
      if (ev.title !== 'NaviSuite test page') throw new Error(`Unexpected page: ${ev.title}`);
    });
    await step('server stops: status turns offline and the offline page shows', async () => {
      await vessel.stopHttps();
      await waitFor('offline state', (e) => e.event === 'state' && e.state === 'offline');
      await waitFor('offline page', (e) => e.event === 'local-page' && e.page === 'offline.html');
    });
    await step('server returns: the client reconnects and keeps the open page', async () => {
      await vessel.startHttps();
      await waitFor('online state', (e) => e.event === 'state' && e.state === 'online');
      const ev = await waitFor('app shown again', (e) => e.event === 'app-resumed');
      if (ev.reloaded) throw new Error('The page was reloaded although it had not failed.');
    });
    await step('client restarted while the server is down: saved settings, offline page', async () => {
      await quit();
      await vessel.stopHttps();
      launch();
      await waitFor('offline page', (e) => e.event === 'local-page' && e.page === 'offline.html');
    });
    await step('server returns: the failed page is loaded again', async () => {
      await vessel.startHttps();
      const ev = await waitFor('app reloaded', (e) => e.event === 'app-resumed');
      if (!ev.reloaded) throw new Error('The failed page was not reloaded.');
      await waitFor('app page', (e) => e.event === 'app-loaded');
    });
    await step('server updated to need a newer client: the mismatch page says so, and it recovers', async () => {
      vessel.state.clients = { api: 1, min_version: '99.0.0' };
      await waitFor('incompatible page', (e) => e.event === 'local-page' && e.page === 'offline.html' && e.kind === 'incompatible');
      vessel.state.clients = { api: 1, min_version: '1.0.0' };
      await waitFor('app shown again', (e) => e.event === 'app-resumed');
    });
    await step('installer mode writes the administrator settings and starts nothing else', async () => {
      const target = path.join(dir, 'installer', 'client-config.json');
      const run = (args) => new Promise((resolve) => {
        const p = spawn(electron, [path.join(__dirname, '..', '..'), '--navisuite-write-managed-config', `--file=${target}`, ...args], { env, stdio: 'ignore' });
        const timer = setTimeout(() => { p.kill(); resolve('timeout'); }, 20000);
        p.once('exit', (code) => { clearTimeout(timer); resolve(code); });
      });
      const fp = parseCaCertificate(pki.caPem).sha256;
      const ok = await run(['--server=nav-server,192.168.1.10', `--fingerprint=${fp}`, '--vessel=12', '--lock']);
      if (ok !== 0) throw new Error(`exit ${ok}`);
      const written = JSON.parse(fs.readFileSync(target, 'utf8'));
      if (!written.lockServer || written.server.vesselId !== 12 || written.server.hosts[1] !== '192.168.1.10') throw new Error(`Unexpected file: ${JSON.stringify(written)}`);
      const bad = await run(['--server=nav-server', '--fingerprint=nonsense']);
      if (bad !== 2) throw new Error(`a bad fingerprint gave exit ${bad}, expected 2`);
      if (!JSON.parse(fs.readFileSync(target, 'utf8')).lockServer) throw new Error('A failed run changed the file.');
    });
    await step('impostor with a certificate from another CA: refused before any request', async () => {
      await quit();
      await vessel.stopHttps();
      await vessel.startHttps(makeVesselPki({ name: 'Impostor' }));
      vessel.state.httpsRequests = 0;
      launch();
      await waitFor('untrusted page', (e) => e.event === 'local-page' && e.page === 'offline.html' && e.kind === 'untrusted');
      await new Promise((r) => setTimeout(r, 3000));
      if (vessel.state.httpsRequests !== 0) throw new Error(`The impostor received ${vessel.state.httpsRequests} HTTPS request(s).`);
      const log = fs.readFileSync(path.join(dir, 'data', 'logs', 'client.log'), 'utf8');
      if (!/Refused certificate for 127\.0\.0\.1/.test(log)) throw new Error('The page view did not check the certificate.');
      if (!/Page failed to load \(-2\d\d /.test(log)) throw new Error('The page view did not report a certificate error.');
    });
    const data = path.join(dir, 'data');
    const saved = ['server.dat', 'server.json'].find((f) => fs.existsSync(path.join(data, f)));
    if (!saved) throw new Error('The server settings were not saved.');
    results.push([`settings saved as ${saved}`, '']);
    console.log('\nSMOKE PASSED');
    passed = true;
    for (const [label, time] of results) console.log(`  ok  ${label}${time ? ` (${time})` : ''}`);
  } catch (err) {
    console.error(`\nSMOKE FAILED: ${err.message}`);
    if (stderr) console.error(stderr.split('\n').slice(-20).join('\n'));
    process.exitCode = 1;
  } finally {
    if (child.exitCode === null) await quit();
    await vessel.close();
    // Kept after a failure, for its client.log.
    if (passed) fs.rmSync(dir, { recursive: true, force: true });
    else console.error(`Client data kept in ${dir}`);
  }
}

main().catch((err) => { console.error(err); process.exit(1); });
