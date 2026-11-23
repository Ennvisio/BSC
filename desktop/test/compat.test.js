'use strict';
const test = require('node:test');
const assert = require('node:assert');
const { checkCompatibility, compareVersions, parseVersion } = require('../src/main/compat');

test('servers from before Checkpoint 8 publish nothing and accept every client', () => {
  assert.deepStrictEqual(checkCompatibility({ app: 'NaviSuite', mode: 'vessel' }, '1.0.0'), { ok: true });
  assert.deepStrictEqual(checkCompatibility(null, '1.0.0'), { ok: true });
});

test('a server needing a newer client says which version', () => {
  const r = checkCompatibility({ clients: { api: 1, min_version: '1.3.0' } }, '1.2.9');
  assert.strictEqual(r.ok, false);
  assert.strictEqual(r.reason, 'client-too-old');
  assert.match(r.message, /1\.3\.0 or later/);
  assert.deepStrictEqual(checkCompatibility({ clients: { api: 1, min_version: '1.3.0' } }, '1.3.0'), { ok: true });
  assert.deepStrictEqual(checkCompatibility({ clients: { api: 1, min_version: '1.3.0' } }, '1.10.0'), { ok: true });
});

test('a server with a newer interface needs a newer client; an older one needs a server update', () => {
  assert.strictEqual(checkCompatibility({ clients: { api: 2, min_version: '1.0.0' } }, '1.0.0').reason, 'client-too-old');
  assert.strictEqual(checkCompatibility({ clients: { api: 0 } }, '1.0.0').reason, 'server-too-old');
});

test('nonsense in the answer is ignored rather than locking users out', () => {
  assert.deepStrictEqual(checkCompatibility({ clients: { api: 'two', min_version: '<script>' } }, '1.0.0'), { ok: true });
  assert.deepStrictEqual(checkCompatibility({ clients: 'x' }, '1.0.0'), { ok: true });
});

test('versions compare numerically and ignore pre-release suffixes', () => {
  assert.strictEqual(compareVersions('1.10.0', '1.9.9'), 1);
  assert.strictEqual(compareVersions('1.0.0-test.abc', '1.0.0'), 0);
  assert.strictEqual(compareVersions('2.0.0', '10.0.0'), -1);
  assert.strictEqual(parseVersion('1.0'), null);
});
