'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const http = require('node:http');
const { hasOpenssl, makeVesselPki } = require('./helpers/pki');
const { startFakeVessel } = require('./helpers/fake-vessel');
const { identifyServer, quickHealth } = require('../src/main/probe');
const { getJson } = require('../src/main/http');
const { parseCaCertificate } = require('../src/main/trust');

const skip = !hasOpenssl() && 'openssl is not installed';

test('a vessel server is identified with its CA, and HTTPS is proven', { skip }, async (t) => {
  const pki = makeVesselPki();
  const server = await startFakeVessel({ pki });
  t.after(() => server.close());

  const found = await identifyServer('127.0.0.1', { httpPort: server.ports.http, httpsPort: server.ports.https });
  assert.equal(found.vesselId, 12);
  assert.equal(found.vesselName, 'MV Test');
  assert.equal(found.release, 'test-release');
  assert.equal(found.caSha256, parseCaCertificate(pki.caPem).sha256);
  assert.ok(await quickHealth('127.0.0.1', { httpPort: server.ports.http }));
});

test('a server whose HTTPS certificate is not from the CA it hands out is refused', { skip }, async (t) => {
  // An impostor answering HTTP with one CA while HTTPS uses another.
  const pki = makeVesselPki();
  const impostor = makeVesselPki();
  const server = await startFakeVessel({ pki, httpsPki: impostor });
  t.after(() => server.close());

  await assert.rejects(identifyServer('127.0.0.1', { httpPort: server.ports.http, httpsPort: server.ports.https }), (err) => err.kind === 'tls');
});

test('HTTPS requests trust only the pinned CA', { skip }, async (t) => {
  const pki = makeVesselPki();
  const other = makeVesselPki();
  const server = await startFakeVessel({ pki });
  t.after(() => server.close());
  const url = `https://127.0.0.1:${server.ports.https}/health`;

  assert.equal((await getJson(url, { ca: pki.caPem })).json.status, 'ok');
  await assert.rejects(getJson(url, { ca: other.caPem }), (err) => err.kind === 'tls' && !/Node\.js/.test(err.message));
  await assert.rejects(getJson(`https://localhost:${server.ports.https}/health`.replace('localhost', '127.0.0.2'), { ca: pki.caPem, timeoutMs: 1000 }), (err) => ['tls', 'network', 'timeout'].includes(err.kind));
  await assert.rejects(getJson(url), (err) => err.kind === 'tls', 'no CA given: no HTTPS');
  server.state.status = 'down';
  assert.equal((await getJson(url, { ca: pki.caPem })).status, 503);
});

test('other web servers are not mistaken for NaviSuite', async (t) => {
  const plain = http.createServer((req, res) => { res.writeHead(200, { 'Content-Type': 'application/json' }); res.end('{"status":"ok"}'); });
  await new Promise((r) => plain.listen(0, '127.0.0.1', r));
  t.after(() => plain.close());
  const port = plain.address().port;

  assert.equal(await quickHealth('127.0.0.1', { httpPort: port }), null);
  await assert.rejects(identifyServer('127.0.0.1', { httpPort: port }), (err) => err.kind === 'not-navisuite');
  await assert.rejects(identifyServer('127.0.0.1', { httpPort: 9, timeoutMs: 500 }), (err) => err.kind === 'unreachable');
});
