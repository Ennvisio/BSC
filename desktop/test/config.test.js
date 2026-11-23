'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { hasOpenssl, makeVesselPki } = require('./helpers/pki');
const { ConfigStore, readManaged } = require('../src/main/config');
const { parseCaCertificate } = require('../src/main/trust');

const skip = !hasOpenssl() && 'openssl is not installed';

// Stands in for Windows DPAPI: reversible, and detects changes.
const fakeSafeStorage = {
  isEncryptionAvailable: () => true,
  encryptString: (s) => Buffer.from(`ENC1${Buffer.from(s).toString('base64')}`),
  decryptString: (b) => {
    const text = b.toString();
    if (!text.startsWith('ENC1')) throw new Error('Error while decrypting the ciphertext provided to safeStorage.decryptString.');
    return Buffer.from(text.slice(4), 'base64').toString();
  },
};

function tmpDir() {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'navisuite-config-'));
  process.once('exit', () => fs.rmSync(dir, { recursive: true, force: true }));
  return dir;
}

function server(pki, extra = {}) {
  return { vesselId: 12, vesselName: 'MV Test', hosts: ['Master-PC', '192.168.1.10'], caPem: pki.caPem, caSha256: parseCaCertificate(pki.caPem).sha256, source: 'discovery', trustedVia: 'user', ...extra };
}

test('the server is saved encrypted and read back', { skip }, () => {
  const pki = makeVesselPki();
  const dir = tmpDir();
  const store = new ConfigStore({ dir, safeStorage: fakeSafeStorage });
  const saved = store.save(server(pki));
  assert.deepEqual(saved.hosts, ['master-pc', '192.168.1.10']);
  assert.equal(saved.activeHost, 'master-pc');
  assert.ok(fs.existsSync(path.join(dir, 'server.dat')));
  assert.ok(!fs.readFileSync(path.join(dir, 'server.dat'), 'utf8').includes('master-pc'), 'not readable as plain text');
  assert.deepEqual(new ConfigStore({ dir, safeStorage: fakeSafeStorage }).load(), saved);
});

test('a changed or damaged file is not trusted', { skip }, () => {
  const pki = makeVesselPki();
  const dir = tmpDir();
  const store = new ConfigStore({ dir, safeStorage: fakeSafeStorage });
  store.save(server(pki));
  fs.writeFileSync(path.join(dir, 'server.dat'), 'tampered');
  assert.equal(store.load(), null);
  assert.match(store.lastError, /decrypting/);
});

test('certificate and fingerprint must agree', { skip }, () => {
  const pki = makeVesselPki();
  const other = makeVesselPki();
  const store = new ConfigStore({ dir: tmpDir(), safeStorage: fakeSafeStorage });
  assert.throws(() => store.save(server(pki, { caSha256: parseCaCertificate(other.caPem).sha256 })), /do not match/);
  assert.throws(() => store.save(server(pki, { hosts: ['bad host'] })), /addresses/);
  assert.throws(() => store.save(server(pki, { caPem: pki.serverPem })), /not a certificate authority/);
});

test('without Windows encryption it falls back to a plain file', { skip }, () => {
  const pki = makeVesselPki();
  const dir = tmpDir();
  const store = new ConfigStore({ dir, safeStorage: { isEncryptionAvailable: () => false } });
  store.save(server(pki));
  assert.ok(fs.existsSync(path.join(dir, 'server.json')));
  assert.equal(store.load().vesselId, 12);
  store.clear();
  assert.equal(store.load(), null);
});

test('administrator settings: defaults, a locked server, and mistakes', () => {
  const dir = tmpDir();
  const file = path.join(dir, 'client-config.json');
  const missing = readManaged(path.join(dir, 'none.json'));
  assert.equal(missing.lockServer, false);
  assert.equal(missing.clearSessionOnExit, true);
  assert.deepEqual(missing.discovery, { enabled: true, port: 47810, sweep: true });

  const fp = Array.from({ length: 32 }, () => 'ab').join(':');
  fs.writeFileSync(file, `﻿${JSON.stringify({ server: { hosts: ['Master-PC'], caSha256: fp, vesselId: 12 }, lockServer: true, discovery: { sweep: false } })}`);
  const managed = readManaged(file);
  assert.equal(managed.lockServer, true);
  assert.deepEqual(managed.server, { hosts: ['master-pc'], httpsPort: 443, httpPort: 80, vesselId: 12, caSha256: fp.toUpperCase() });
  assert.equal(managed.discovery.sweep, false);

  const bad = [
    [{ lockServer: true }, /needs a "server"/],
    [{ server: { hosts: [] } }, /server.hosts/],
    [{ server: { hosts: ['x'], caSha256: 'AB' } }, /fingerprint/],
    [{ clearSessionOnExit: 'yes' }, /true or false/],
    [{ discovery: { port: 0 } }, /discovery port/],
  ];
  for (const [content, message] of bad) {
    fs.writeFileSync(file, JSON.stringify(content));
    assert.throws(() => readManaged(file), message);
  }
  fs.writeFileSync(file, '{oops');
  assert.throws(() => readManaged(file), /not valid JSON/);
});
