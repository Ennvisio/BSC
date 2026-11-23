'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { hasOpenssl, makeVesselPki } = require('./helpers/pki');
const { parseCaCertificate, verifyServerCertificate, displayFingerprint, sameFingerprint, isFingerprint } = require('../src/main/trust');

const skip = !hasOpenssl() && 'openssl is not installed';

test('the vessel CA is read and fingerprinted', { skip }, () => {
  const pki = makeVesselPki();
  const ca = parseCaCertificate(pki.caPem);
  assert.ok(isFingerprint(ca.sha256));
  assert.match(ca.subject, /NaviSuite Vessel CA/);
  assert.equal(parseCaCertificate(Buffer.from(pki.caPem)).sha256, ca.sha256);
  assert.throws(() => parseCaCertificate(pki.serverPem), /not a certificate authority/);
  assert.throws(() => parseCaCertificate('hello'), /not a certificate/);
  assert.throws(() => parseCaCertificate(''), /size/);
  assert.throws(() => parseCaCertificate(pki.caPem, new Date('2100-01-01')), /expired/);
});

test('a server certificate from the pinned CA, for the right name, is accepted', { skip }, () => {
  const pki = makeVesselPki({ dns: ['test-master', 'localhost'], ips: ['127.0.0.1', '192.168.10.5'] });
  for (const host of ['test-master', 'localhost', '127.0.0.1', '192.168.10.5']) {
    assert.deepEqual(verifyServerCertificate({ leafPem: pki.serverPem, caPem: pki.caPem, host }), { ok: true }, host);
  }
});

test('anything else is refused', { skip }, () => {
  const pki = makeVesselPki();
  const other = makeVesselPki();
  const check = (o) => verifyServerCertificate({ leafPem: pki.serverPem, caPem: pki.caPem, host: 'test-master', ...o });
  assert.match(check({ host: 'other-pc' }).reason, /not made out to other-pc/);
  assert.match(check({ host: '192.168.10.99' }).reason, /not made out/);
  assert.match(check({ caPem: other.caPem }).reason, /not issued by this vessel/);
  assert.match(check({ leafPem: other.serverPem }).reason, /not issued by this vessel/);
  assert.match(check({ leafPem: pki.caPem }).reason, /presented a CA/);
  assert.match(check({ caPem: pki.serverPem }).reason, /not a CA/);
  assert.match(check({ now: new Date('2100-01-01') }).reason, /expired/);
  assert.match(check({ leafPem: 'junk' }).reason, /unreadable/);
});

test('fingerprints are compared exactly and shown in short lines', () => {
  const fp = Array.from({ length: 32 }, (_, i) => i.toString(16).padStart(2, '0').toUpperCase()).join(':');
  assert.ok(sameFingerprint(fp, fp.toLowerCase()));
  assert.ok(!sameFingerprint(fp, fp.replace(/^00/, '01')));
  assert.ok(!sameFingerprint(null, fp));
  assert.equal(displayFingerprint(fp).split('\n').length, 4);
  assert.equal(displayFingerprint('nonsense'), '');
});
