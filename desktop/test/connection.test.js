'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { ConnectionMonitor } = require('../src/main/connection');

function fakeTimers() {
  const queue = [];
  return {
    queue,
    setTimeout: (fn, ms) => { const t = { fn, ms }; queue.push(t); return t; },
    clearTimeout: (t) => { const i = queue.indexOf(t); if (i >= 0) queue.splice(i, 1); },
    async fire() { const t = queue.shift(); t.fn(); await new Promise((r) => setImmediate(r)); return t.ms; },
  };
}

async function settle() { await new Promise((r) => setImmediate(r)); }

test('checks every 10 s while connected and backs off while offline', async () => {
  const timers = fakeTimers();
  const results = ['online', 'offline', 'offline', 'offline', 'offline', 'offline', 'offline', 'degraded'];
  const states = [];
  let reconnected = 0;
  const m = new ConnectionMonitor({ check: async () => ({ state: results.shift() }), timers });
  m.on('state', (s) => states.push(s));
  m.on('reconnected', () => reconnected++);
  m.start();
  await settle();

  const delays = [];
  for (let i = 0; i < 7; i++) delays.push(await timers.fire());
  assert.deepEqual(delays, [10000, 2000, 4000, 8000, 15000, 30000, 30000]);
  assert.deepEqual(states.map((s) => s.state), ['online', 'offline', 'offline', 'offline', 'offline', 'offline', 'offline', 'degraded']);
  assert.equal(states[6].failures, 6);
  assert.ok(states[6].unreachableSince);
  assert.equal(states[7].unreachableSince, null);
  assert.equal(reconnected, 1, 'one reconnect after the outage');
  assert.equal(timers.queue[0].ms, 10000);
});

test('"retry now" checks at once, and a failing check counts as offline', async () => {
  const timers = fakeTimers();
  let calls = 0;
  const m = new ConnectionMonitor({ check: async () => { calls++; throw new Error('boom'); }, timers });
  m.start();
  await settle();
  assert.equal(m.state.state, 'offline');
  assert.equal(m.state.detail, 'boom');
  m.checkNow();
  await settle();
  assert.equal(calls, 2);
  assert.equal(timers.queue.length, 1, 'the old timer was replaced');
  m.stop();
  assert.equal(timers.queue.length, 0);
});

test('the first success after starting is not reported as a reconnect', async () => {
  const timers = fakeTimers();
  let reconnected = 0;
  const m = new ConnectionMonitor({ check: async () => ({ state: 'online' }), timers });
  m.on('reconnected', () => reconnected++);
  m.start();
  await settle();
  assert.equal(reconnected, 0);
});

test('an untrusted or wrong server is not reachable, and recovering from it is a reconnect', async () => {
  const timers = fakeTimers();
  const results = ['untrusted', 'wrong-server', 'down'];
  let reconnected = 0;
  const m = new ConnectionMonitor({ check: async () => ({ state: results.shift() }), timers });
  m.on('reconnected', () => reconnected++);
  m.start();
  await settle();
  assert.equal(m.state.failures, 1);
  await timers.fire();
  assert.equal(m.state.failures, 2);
  await timers.fire();
  assert.equal(m.state.state, 'down');
  assert.equal(reconnected, 1);
});
