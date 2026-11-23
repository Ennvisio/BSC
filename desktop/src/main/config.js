'use strict';
// Where the client keeps its settings.
//
// server.dat   The chosen server, encrypted with Windows DPAPI through
//              Electron's safeStorage: only this Windows user on this PC can
//              read it, and a changed file fails to decrypt instead of being
//              trusted. Holds the pinned CA certificate.
// Managed file %ProgramData%\NaviSuite Client\client-config.json, written by
//              the installer or IT and read-only for users. It can fix the
//              server and its fingerprint and lock them.

const fs = require('node:fs');
const path = require('node:path');
const { normaliseHost, validPort } = require('./hosts');
const { parseCaCertificate, isFingerprint, sameFingerprint } = require('./trust');

const DEFAULTS = Object.freeze({
  lockServer: false,
  clearSessionOnExit: true,
  allowDevTools: false,
  discovery: { enabled: true, port: 47810, sweep: true },
});

function defaultManagedPath() {
  if (process.env.NAVISUITE_MANAGED_CONFIG) return process.env.NAVISUITE_MANAGED_CONFIG;
  const programData = process.env.ProgramData || process.env.PROGRAMDATA || 'C:\\ProgramData';
  return path.join(programData, 'NaviSuite Client', 'client-config.json');
}

/** Checks a saved server entry. Returns a clean copy or throws. */
function validateServer(input) {
  if (!input || typeof input !== 'object') throw new Error('No server.');
  const hosts = Array.isArray(input.hosts) ? input.hosts.map(normaliseHost) : [];
  if (hosts.length === 0 || hosts.length > 8 || hosts.some((h) => !h)) throw new Error('Invalid server addresses.');
  const unique = [...new Set(hosts)];
  const httpsPort = input.httpsPort ?? 443;
  const httpPort = input.httpPort ?? 80;
  if (!validPort(httpsPort) || !validPort(httpPort)) throw new Error('Invalid ports.');
  const ca = parseCaCertificate(input.caPem);
  if (!sameFingerprint(ca.sha256, input.caSha256)) throw new Error('Certificate and fingerprint do not match.');
  const vesselId = input.vesselId === null || input.vesselId === undefined ? null : input.vesselId;
  if (vesselId !== null && (!Number.isInteger(vesselId) || vesselId < 1)) throw new Error('Invalid vessel id.');
  return {
    vesselId,
    vesselName: typeof input.vesselName === 'string' ? input.vesselName.slice(0, 200) : null,
    hosts: unique,
    activeHost: unique.includes(input.activeHost) ? input.activeHost : unique[0],
    httpsPort,
    httpPort,
    caPem: ca.pem,
    caSha256: ca.sha256,
    source: ['discovery', 'manual', 'managed'].includes(input.source) ? input.source : 'manual',
    trustedVia: ['user', 'managed', 'windows'].includes(input.trustedVia) ? input.trustedVia : 'user',
    confirmedAt: typeof input.confirmedAt === 'string' ? input.confirmedAt : new Date().toISOString(),
  };
}

/** Reads and checks the administrator's file. Missing file: defaults. */
function readManaged(file = defaultManagedPath()) {
  let raw;
  try {
    raw = fs.readFileSync(file, 'utf8');
  } catch {
    return { ...DEFAULTS, discovery: { ...DEFAULTS.discovery }, server: null, source: null };
  }
  let data;
  try {
    data = JSON.parse(raw.replace(/^\uFEFF/, ''));
  } catch {
    throw new Error(`${file} is not valid JSON.`);
  }
  const result = { ...DEFAULTS, discovery: { ...DEFAULTS.discovery }, server: null, source: file };
  for (const key of ['lockServer', 'clearSessionOnExit', 'allowDevTools']) {
    if (data[key] !== undefined) {
      if (typeof data[key] !== 'boolean') throw new Error(`${file}: "${key}" must be true or false.`);
      result[key] = data[key];
    }
  }
  if (data.discovery !== undefined) {
    const d = data.discovery;
    if (typeof d !== 'object' || d === null) throw new Error(`${file}: "discovery" must be an object.`);
    if (d.enabled !== undefined) result.discovery.enabled = d.enabled === true;
    if (d.sweep !== undefined) result.discovery.sweep = d.sweep === true;
    if (d.port !== undefined) {
      if (!validPort(d.port)) throw new Error(`${file}: invalid discovery port.`);
      result.discovery.port = d.port;
    }
  }
  if (data.server !== undefined && data.server !== null) {
    const s = data.server;
    const hosts = Array.isArray(s.hosts) ? s.hosts.map(normaliseHost) : [];
    if (hosts.length === 0 || hosts.some((h) => !h)) throw new Error(`${file}: "server.hosts" must list host names or IP addresses.`);
    if (s.caSha256 !== undefined && !isFingerprint(String(s.caSha256).toUpperCase())) throw new Error(`${file}: "server.caSha256" must be a SHA-256 fingerprint (AB:CD:...).`);
    if (s.vesselId !== undefined && (!Number.isInteger(s.vesselId) || s.vesselId < 1)) throw new Error(`${file}: invalid "server.vesselId".`);
    const httpsPort = s.httpsPort ?? 443;
    const httpPort = s.httpPort ?? 80;
    if (!validPort(httpsPort) || !validPort(httpPort)) throw new Error(`${file}: invalid server ports.`);
    result.server = {
      hosts: [...new Set(hosts)],
      httpsPort,
      httpPort,
      vesselId: s.vesselId ?? null,
      caSha256: s.caSha256 ? String(s.caSha256).toUpperCase() : null,
    };
  }
  if (result.lockServer && !result.server) throw new Error(`${file}: "lockServer" needs a "server".`);
  return result;
}

class ConfigStore {
  /**
   * @param {object} o
   * @param {string} o.dir           per-user folder (Electron userData)
   * @param {object} [o.safeStorage] Electron safeStorage; plain JSON when unavailable
   */
  constructor({ dir, safeStorage }) {
    this.dir = dir;
    this.safeStorage = safeStorage;
    this.encryptedFile = path.join(dir, 'server.dat');
    this.plainFile = path.join(dir, 'server.json');
    this.lastError = null;
  }

  get canEncrypt() {
    try {
      return Boolean(this.safeStorage && this.safeStorage.isEncryptionAvailable());
    } catch {
      return false;
    }
  }

  /** The saved server, or null. A damaged or tampered file counts as none. */
  load() {
    this.lastError = null;
    try {
      if (fs.existsSync(this.encryptedFile)) {
        if (!this.canEncrypt) throw new Error('Saved settings are encrypted but Windows encryption is not available.');
        const json = this.safeStorage.decryptString(fs.readFileSync(this.encryptedFile));
        return validateServer(JSON.parse(json).server);
      }
      if (fs.existsSync(this.plainFile)) {
        return validateServer(JSON.parse(fs.readFileSync(this.plainFile, 'utf8')).server);
      }
    } catch (err) {
      this.lastError = err.message;
    }
    return null;
  }

  save(server) {
    const clean = validateServer(server);
    fs.mkdirSync(this.dir, { recursive: true });
    const json = JSON.stringify({ version: 1, server: clean });
    if (this.canEncrypt) {
      this.writeAtomic(this.encryptedFile, this.safeStorage.encryptString(json));
      fs.rmSync(this.plainFile, { force: true });
    } else {
      this.writeAtomic(this.plainFile, json, 0o600);
    }
    return clean;
  }

  clear() {
    fs.rmSync(this.encryptedFile, { force: true });
    fs.rmSync(this.plainFile, { force: true });
  }

  writeAtomic(file, data, mode) {
    const tmp = `${file}.${process.pid}.tmp`;
    fs.writeFileSync(tmp, data, mode ? { mode } : undefined);
    fs.renameSync(tmp, file);
  }
}

/**
 * Writes the administrator settings file from the installer's command line
 * (Checkpoint 8): NaviSuite-Client-Setup.exe /S /SERVER=nav-server,192.168.1.10
 * /FINGERPRINT=AB:CD:... /VESSEL=12 /LOCK
 *
 * The result is read back with readManaged, the same check the client runs
 * at every start, before it replaces anything: a mistyped option leaves the
 * existing file untouched and fails the call. Settings already in the file
 * that the command line does not mention (discovery, clearSessionOnExit,
 * allowDevTools) are kept.
 */
function writeManagedConfig(file, { hosts, caSha256 = null, vesselId = null, lockServer = false, httpsPort, httpPort }) {
  if (!Array.isArray(hosts) || hosts.length === 0) throw new Error('No server address given (/SERVER=).');
  let existing = {};
  try {
    existing = JSON.parse(fs.readFileSync(file, 'utf8').replace(/^\uFEFF/, ''));
    if (typeof existing !== 'object' || existing === null || Array.isArray(existing)) existing = {};
  } catch { /* none yet, or unreadable: replaced */ }

  const server = { hosts };
  if (caSha256) server.caSha256 = String(caSha256).trim().toUpperCase();
  if (vesselId !== null && vesselId !== undefined) server.vesselId = vesselId;
  if (httpsPort !== undefined) server.httpsPort = httpsPort;
  if (httpPort !== undefined) server.httpPort = httpPort;
  const data = { ...existing, lockServer: Boolean(lockServer), server };

  fs.mkdirSync(path.dirname(file), { recursive: true });
  const tmp = `${file}.${process.pid}.tmp`;
  fs.writeFileSync(tmp, `${JSON.stringify(data, null, 2)}\n`);
  try {
    readManaged(tmp); // throws on anything the client would refuse
    fs.renameSync(tmp, file);
  } catch (err) {
    fs.rmSync(tmp, { force: true });
    throw err;
  }
  return readManaged(file);
}

module.exports = { ConfigStore, readManaged, validateServer, defaultManagedPath, writeManagedConfig, DEFAULTS };
