'use strict';
// The client's discovery against the real server responder:
// php artisan navisuite:discovery.

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { spawn, execFileSync } = require('node:child_process');
const { discover } = require('../src/main/discovery');

const ROOT = path.join(__dirname, '..', '..');
let php = null;
try {
  execFileSync('php', ['-v'], { stdio: 'ignore' });
  php = 'php';
} catch { /* skipped below */ }

test('the client finds the PHP responder and understands its answer', { skip: !php && 'php is not installed', timeout: 30000 }, async (t) => {
  const port = 40000 + Math.floor(Math.random() * 19000);
  const child = spawn(php, ['artisan', 'navisuite:discovery', '--bind=127.0.0.1', `--port=${port}`, '--max-seconds=20'], {
    cwd: ROOT,
    env: {
      ...process.env, APP_ENV: 'testing', APP_MODE: 'vessel', APP_VESSEL_ID: '34', APP_URL: 'https://interop-master',
      APP_RELEASE: 'interop', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', CACHE_DRIVER: 'array', CACHE_STORE: 'array',
    },
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  let output = '';
  child.stdout.on('data', (b) => { output += b; });
  child.stderr.on('data', (b) => { output += b; });
  t.after(() => child.kill());

  let found = [];
  for (let i = 0; i < 20 && found.length === 0; i++) {
    found = await discover({ port, targets: ['127.0.0.1'], timeoutMs: 500, attempts: 1 });
  }
  assert.equal(found.length, 1, `no answer from the PHP responder. Output:\n${output}`);
  assert.equal(found[0].vesselId, 34);
  assert.equal(found[0].host, 'interop-master');
  assert.equal(found[0].release, 'interop');
  assert.equal(found[0].httpsPort, 443);

  const otherVessel = await discover({ port, targets: ['127.0.0.1'], timeoutMs: 500, attempts: 1, vesselId: 99 });
  assert.deepEqual(otherVessel, [], 'a server stays silent for another vessel');
});
