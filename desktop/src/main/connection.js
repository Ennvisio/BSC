'use strict';
// Watches the connection to the server and decides when to try again.
//
// States:
//   connecting    first check not finished
//   online        /health says ok
//   degraded      /health says degraded: usable, something needs attention
//   down          /health says down: the server answers but cannot work
//   offline       no answer
//   untrusted     the server's certificate is not from the pinned authority
//   wrong-server  a different vessel's server answers at this address
//   incompatible  the server and this client are versions that cannot work
//                 together (compat.js); checked again like offline
//
// While reachable, it checks every 10 s. While not, it retries after 2, 4,
// 8, 15, then every 30 s, and a manual "retry now" runs at once.

const { EventEmitter } = require('node:events');

const REACHABLE = new Set(['online', 'degraded', 'down']);

class ConnectionMonitor extends EventEmitter {
  constructor({ check, onlineIntervalMs = 10000, backoffMs = [2000, 4000, 8000, 15000, 30000], now = () => Date.now(), timers = { setTimeout, clearTimeout } }) {
    super();
    this.checkFn = check;
    this.onlineIntervalMs = onlineIntervalMs;
    this.backoffMs = backoffMs;
    this.now = now;
    this.timers = timers;
    this.running = false;
    this.inFlight = false;
    this.again = false;
    this.timer = null;
    this.failures = 0;
    this.current = { state: 'connecting', since: now(), failures: 0, unreachableSince: null, nextCheckAt: null };
  }

  get state() {
    return this.current;
  }

  start() {
    if (this.running) return;
    this.running = true;
    this.run();
  }

  stop() {
    this.running = false;
    if (this.timer) this.timers.clearTimeout(this.timer);
    this.timer = null;
  }

  /** Check now instead of waiting for the next scheduled check. */
  checkNow() {
    if (!this.running) return;
    if (this.timer) this.timers.clearTimeout(this.timer);
    this.timer = null;
    this.run();
  }

  async run() {
    if (this.inFlight) {
      this.again = true;
      return;
    }
    this.inFlight = true;
    let result;
    try {
      result = await this.checkFn();
    } catch (err) {
      result = { state: 'offline', detail: err && err.message };
    }
    this.inFlight = false;
    if (!this.running) return;

    const previous = this.current;
    const reachable = REACHABLE.has(result.state);
    this.failures = reachable ? 0 : this.failures + 1;
    const delay = reachable ? this.onlineIntervalMs : this.backoffMs[Math.min(this.failures, this.backoffMs.length) - 1];
    const now = this.now();

    this.current = {
      ...result,
      since: previous.state === result.state ? previous.since : now,
      failures: this.failures,
      unreachableSince: reachable ? null : (previous.unreachableSince ?? now),
      nextCheckAt: now + delay,
    };
    this.emit('state', this.current);
    if (reachable && !REACHABLE.has(previous.state) && previous.state !== 'connecting') {
      this.emit('reconnected', this.current);
    }

    if (this.again) {
      this.again = false;
      this.run();
    } else {
      this.timer = this.timers.setTimeout(() => {
        this.timer = null;
        this.run();
      }, delay);
    }
  }
}

module.exports = { ConnectionMonitor, REACHABLE };
