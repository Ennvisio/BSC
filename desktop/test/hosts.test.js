'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { normaliseHost, isPrivateIPv4, httpsOrigin, httpOrigin } = require('../src/main/hosts');

test('addresses typed by users are reduced to a host name or IPv4 address', () => {
  assert.equal(normaliseHost(' 192.168.1.10 '), '192.168.1.10');
  assert.equal(normaliseHost('Master-PC'), 'master-pc');
  assert.equal(normaliseHost('https://master-pc/login'), 'master-pc');
  assert.equal(normaliseHost('master-pc.ship.local.'), 'master-pc.ship.local');
  for (const bad of ['', ' ', 'master pc', 'master-pc:8443', 'javascript:alert(1)', 'file:///c:/x', '-bad', 'a'.repeat(254), null, 42]) {
    assert.equal(normaliseHost(bad), null, String(bad));
  }
});

test('only ship-network addresses count as private', () => {
  for (const ip of ['10.1.2.3', '172.16.0.1', '172.31.255.255', '192.168.0.5', '169.254.1.1', '127.0.0.1']) assert.ok(isPrivateIPv4(ip), ip);
  for (const ip of ['8.8.8.8', '172.32.0.1', '100.64.0.1', '192.169.0.1', 'master-pc', '::1']) assert.ok(!isPrivateIPv4(ip), ip);
});

test('origins leave out default ports', () => {
  assert.equal(httpsOrigin('master-pc'), 'https://master-pc');
  assert.equal(httpsOrigin('10.0.0.5', 8443), 'https://10.0.0.5:8443');
  assert.equal(httpOrigin('10.0.0.5'), 'http://10.0.0.5');
});
