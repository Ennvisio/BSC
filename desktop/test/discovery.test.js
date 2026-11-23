'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const dgram = require('node:dgram');
const d = require('../src/main/discovery');

const FP = Array.from({ length: 32 }, () => 'AB').join(':');

function reply(nonce, overrides = {}) {
  return Buffer.from(JSON.stringify({
    type: 'navisuite.server', v: 1, nonce, app: 'NaviSuite', vessel: { id: 12, name: 'MV Test' },
    host: 'master-pc', https_port: 443, http_port: 80, ca_sha256: FP, release: 'r1', ...overrides,
  }));
}

test('the question has the documented shape', () => {
  const nonce = d.newNonce();
  assert.match(nonce, /^[0-9a-f]{32}$/);
  assert.deepEqual(JSON.parse(d.buildRequest(nonce)), { type: 'navisuite.discover', v: 1, nonce });
  assert.deepEqual(JSON.parse(d.buildRequest(nonce, 12)), { type: 'navisuite.discover', v: 1, nonce, vessel: 12 });
});

test('answers are checked field by field', () => {
  const n = 'a'.repeat(32);
  assert.deepEqual(d.parseReply(reply(n), n), {
    vesselId: 12, vesselName: 'MV Test', host: 'master-pc', httpsPort: 443, httpPort: 80, caSha256: FP, release: 'r1',
  });
  assert.equal(d.parseReply(reply(n, { ca_sha256: null, vessel: { id: 3, name: null } }), n).caSha256, null);
  const bad = [
    reply('b'.repeat(32)), reply(n, { type: 'x' }), reply(n, { app: 'Other' }), reply(n, { vessel: { id: 0 } }),
    reply(n, { vessel: null }), reply(n, { host: 'bad host' }), reply(n, { https_port: 70000 }),
    reply(n, { ca_sha256: 'AB:CD' }), reply(n, { vessel: { id: 1, name: 'x'.repeat(201) } }),
    Buffer.from('not json'), Buffer.from('[]'), Buffer.alloc(3000, 32),
  ];
  for (const b of bad) assert.equal(d.parseReply(b, n), null, b.toString().slice(0, 80));
});

const IFACES = {
  Ethernet: [
    { address: '192.168.10.23', netmask: '255.255.255.0', family: 'IPv4', internal: false },
    { address: 'fe80::1', netmask: 'ffff:ffff:ffff:ffff::', family: 'IPv6', internal: false },
  ],
  'Engine LAN': [{ address: '10.20.0.7', netmask: '255.255.0.0', family: 'IPv4', internal: false }],
  WiFi: [{ address: '169.254.3.3', netmask: '255.255.0.0', family: 'IPv4', internal: false }],
  Internet: [{ address: '203.0.113.5', netmask: '255.255.255.0', family: 'IPv4', internal: false }],
  Loopback: [{ address: '127.0.0.1', netmask: '255.0.0.0', family: 'IPv4', internal: true }],
};

test('questions go to each private network\'s broadcast address', () => {
  assert.deepEqual(d.broadcastTargets(IFACES).sort(), ['10.20.255.255', '192.168.10.255', '255.255.255.255']);
});

test('the sweep covers the /24 around each private address, not this PC', () => {
  const targets = d.sweepTargets(IFACES);
  assert.equal(targets.length, 253 * 2);
  assert.ok(targets.includes('192.168.10.1') && targets.includes('192.168.10.254'));
  assert.ok(!targets.includes('192.168.10.23') && !targets.includes('192.168.10.0') && !targets.includes('192.168.10.255'));
  assert.ok(targets.includes('10.20.0.1') && !targets.some((t) => t.startsWith('203.') || t.startsWith('169.254.')));
});

test('sweep runs probes concurrently and keeps the hits', async () => {
  let active = 0;
  let peak = 0;
  const found = await d.sweep({
    targets: Array.from({ length: 100 }, (_, i) => `10.0.0.${i + 1}`),
    concurrency: 10,
    probe: async (ip) => {
      active++; peak = Math.max(peak, active);
      await new Promise((r) => setTimeout(r, 2));
      active--;
      if (ip === '10.0.0.50') return ip;
      if (ip === '10.0.0.60') throw new Error('refused');
      return null;
    },
  });
  assert.deepEqual(found, ['10.0.0.50']);
  assert.equal(peak, 10);
});

test('discover() collects and merges real UDP answers', async (t) => {
  const responder = dgram.createSocket('udp4');
  const seen = [];
  responder.on('message', (msg, rinfo) => {
    const q = JSON.parse(msg);
    seen.push(q);
    responder.send(reply(q.nonce), rinfo.port, rinfo.address);
    responder.send(reply('f'.repeat(32)), rinfo.port, rinfo.address); // wrong nonce: ignored
    responder.send(Buffer.from('junk'), rinfo.port, rinfo.address);
  });
  await new Promise((r) => responder.bind(0, '127.0.0.1', r));
  t.after(() => responder.close());

  const found = await d.discover({ port: responder.address().port, targets: ['127.0.0.1'], timeoutMs: 800, attempts: 2, vesselId: 12 });
  assert.equal(seen.length, 2);
  assert.equal(seen[0].vessel, 12);
  assert.equal(found.length, 1, 'repeated answers from one server are merged');
  assert.deepEqual(found[0].addresses, ['127.0.0.1']);
  assert.equal(found[0].vesselName, 'MV Test');
});

test('discover() ends quietly when nobody answers', async () => {
  const found = await d.discover({ port: 9, targets: ['127.0.0.1'], timeoutMs: 300, attempts: 1 });
  assert.deepEqual(found, []);
});
