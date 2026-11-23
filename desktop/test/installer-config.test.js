'use strict';
// The installer's /SERVER= /FINGERPRINT= /VESSEL= /LOCK options (Checkpoint 8).
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { writeManagedConfig, readManaged } = require('../src/main/config');

const FP = Array.from({ length: 32 }, (_, i) => (i * 7 % 256).toString(16).padStart(2, '0').toUpperCase()).join(':');

function file() {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'navisuite-installer-'));
  process.once('exit', () => fs.rmSync(dir, { recursive: true, force: true }));
  return path.join(dir, 'NaviSuite Client', 'client-config.json');
}

test('writes a file the client accepts, pinned and locked', () => {
  const f = file();
  const result = writeManagedConfig(f, { hosts: ['NAV-Server', '192.168.1.10'], caSha256: FP.toLowerCase(), vesselId: 12, lockServer: true });
  assert.deepEqual(result.server.hosts, ['nav-server', '192.168.1.10']);
  assert.equal(result.server.caSha256, FP);
  assert.equal(result.server.vesselId, 12);
  assert.equal(result.lockServer, true);
  assert.deepEqual(readManaged(f).server, result.server);
});

test('a mistyped option fails and leaves the existing file as it was', () => {
  const f = file();
  writeManagedConfig(f, { hosts: ['nav-server'] });
  const before = fs.readFileSync(f, 'utf8');
  assert.throws(() => writeManagedConfig(f, { hosts: ['nav-server'], caSha256: 'not-a-fingerprint' }), /fingerprint/);
  assert.throws(() => writeManagedConfig(f, { hosts: ['bad host name!'] }), /server\.hosts/);
  assert.throws(() => writeManagedConfig(f, { hosts: ['nav-server'], vesselId: Number('x') }), /vesselId/);
  assert.throws(() => writeManagedConfig(f, { hosts: [] }), /No server address/);
  assert.equal(fs.readFileSync(f, 'utf8'), before);
  assert.deepEqual(fs.readdirSync(path.dirname(f)), ['client-config.json'], 'no temporary files left behind');
});

test('settings IT already set are kept on upgrade', () => {
  const f = file();
  fs.mkdirSync(path.dirname(f), { recursive: true });
  fs.writeFileSync(f, JSON.stringify({ clearSessionOnExit: false, discovery: { enabled: false } }));
  const result = writeManagedConfig(f, { hosts: ['192.168.1.10'] });
  assert.equal(result.clearSessionOnExit, false);
  assert.equal(result.discovery.enabled, false);
  assert.equal(result.lockServer, false);
});
