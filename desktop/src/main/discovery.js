'use strict';
// Finding the vessel server on the LAN.
//
// 1. UDP: broadcast {"type":"navisuite.discover",...} to port 47810 and
//    collect the servers' answers (NaviSuite-Discovery service).
// 2. If nobody answers: ask http://<ip>/health on every address of this
//    PC's own /24 networks (the "sweep").
//
// Both only produce candidates. Nothing found here is trusted until
// probe.identifyServer() and the certificate check have run.

const dgram = require('node:dgram');
const os = require('node:os');
const crypto = require('node:crypto');
const net = require('node:net');
const { isPrivateIPv4, isValidHost, validPort, ipToInt, intToIp } = require('./hosts');
const { isFingerprint } = require('./trust');

const DEFAULT_PORT = 47810;
const REQUEST_TYPE = 'navisuite.discover';
const REPLY_TYPE = 'navisuite.server';

function newNonce() {
  return crypto.randomBytes(16).toString('hex');
}

function buildRequest(nonce, vesselId) {
  const message = { type: REQUEST_TYPE, v: 1, nonce };
  if (Number.isInteger(vesselId) && vesselId > 0) message.vessel = vesselId;
  return Buffer.from(JSON.stringify(message), 'utf8');
}

/** A validated answer, or null. Unknown fields are ignored. */
function parseReply(buffer, nonce) {
  if (!Buffer.isBuffer(buffer) || buffer.length > 2048) return null;
  let data;
  try {
    data = JSON.parse(buffer.toString('utf8'));
  } catch {
    return null;
  }
  if (!data || typeof data !== 'object' || Array.isArray(data)) return null;
  if (data.type !== REPLY_TYPE || !Number.isInteger(data.v) || data.v < 1 || data.nonce !== nonce) return null;
  if (data.app !== 'NaviSuite') return null;
  const vessel = data.vessel;
  if (!vessel || !Number.isInteger(vessel.id) || vessel.id < 1) return null;
  if (vessel.name !== null && vessel.name !== undefined && (typeof vessel.name !== 'string' || vessel.name.length > 200)) return null;
  const host = typeof data.host === 'string' ? data.host.toLowerCase() : null;
  if (host !== null && !isValidHost(host)) return null;
  const httpsPort = data.https_port ?? 443;
  const httpPort = data.http_port ?? 80;
  if (!validPort(httpsPort) || !validPort(httpPort)) return null;
  if (data.ca_sha256 !== null && data.ca_sha256 !== undefined && !isFingerprint(data.ca_sha256)) return null;
  return {
    vesselId: vessel.id,
    vesselName: vessel.name || null,
    host,
    httpsPort,
    httpPort,
    caSha256: data.ca_sha256 || null,
    release: typeof data.release === 'string' ? data.release.slice(0, 64) : null,
  };
}

/** This PC's IPv4 addresses on private networks. */
function localNetworks(interfaces = os.networkInterfaces()) {
  const result = [];
  for (const [name, addresses] of Object.entries(interfaces || {})) {
    for (const a of addresses || []) {
      const family = a.family === 4 ? 'IPv4' : a.family;
      if (family !== 'IPv4' || a.internal || !isPrivateIPv4(a.address) || a.address.startsWith('169.254.')) continue;
      if (!net.isIPv4(a.netmask)) continue;
      result.push({ name, address: a.address, netmask: a.netmask });
    }
  }
  return result;
}

/** Where to send the question: each network's broadcast address, plus 255.255.255.255. */
function broadcastTargets(interfaces) {
  const targets = new Set(['255.255.255.255']);
  for (const n of localNetworks(interfaces)) {
    const broadcast = (ipToInt(n.address) | (~ipToInt(n.netmask) >>> 0)) >>> 0;
    targets.add(intToIp(broadcast));
  }
  return [...targets];
}

/**
 * Broadcasts the question and collects answers for timeoutMs.
 * Resolves a list of candidates: { vesselId, vesselName, host, addresses,
 * httpsPort, httpPort, caSha256, release }. Answers that claim the same
 * vessel and certificate are merged; their addresses are combined.
 */
function discover({
  port = DEFAULT_PORT, timeoutMs = 2500, attempts = 3, vesselId, targets, interfaces,
  createSocket = () => dgram.createSocket({ type: 'udp4' }),
} = {}) {
  const nonce = newNonce();
  const message = buildRequest(nonce, vesselId);
  const destinations = targets || broadcastTargets(interfaces);
  const found = new Map();

  return new Promise((resolve) => {
    const socket = createSocket();
    const timers = [];
    let finished = false;

    const finish = () => {
      if (finished) return;
      finished = true;
      timers.forEach(clearTimeout);
      try { socket.close(); } catch { /* already closed */ }
      resolve([...found.values()]);
    };

    socket.on('error', finish);
    socket.on('message', (buffer, rinfo) => {
      if (!isPrivateIPv4(rinfo.address)) return;
      const reply = parseReply(buffer, nonce);
      if (!reply) return;
      if (vesselId && reply.vesselId !== vesselId) return;
      const key = `${reply.vesselId}|${reply.caSha256 || rinfo.address}`;
      const existing = found.get(key);
      if (existing) {
        if (!existing.addresses.includes(rinfo.address)) existing.addresses.push(rinfo.address);
      } else {
        found.set(key, { ...reply, addresses: [rinfo.address] });
      }
    });

    socket.bind(0, () => {
      try { socket.setBroadcast(true); } catch { /* unicast targets still work */ }
      for (let i = 0; i < attempts; i++) {
        timers.push(setTimeout(() => {
          for (const target of destinations) {
            socket.send(message, port, target, () => { /* unreachable networks are skipped */ });
          }
        }, i * 300));
      }
      timers.push(setTimeout(finish, timeoutMs));
    });
  });
}

/**
 * Addresses to ask during a sweep: the /24 around each of this PC's private
 * addresses (at most 4 networks), without the PC itself.
 */
function sweepTargets(interfaces, maxNetworks = 4) {
  const networks = localNetworks(interfaces).slice(0, maxNetworks);
  const own = new Set(networks.map((n) => n.address));
  const targets = new Set();
  for (const n of networks) {
    const base = (ipToInt(n.address) & 0xffffff00) >>> 0;
    for (let i = 1; i < 255; i++) {
      const ip = intToIp((base + i) >>> 0);
      if (!own.has(ip)) targets.add(ip);
    }
  }
  return [...targets];
}

/**
 * Asks probe(ip) for each target, a few at a time. probe resolves a
 * candidate or null. Resolves the candidates found.
 */
async function sweep({ targets, probe, concurrency = 48, onProgress }) {
  const results = [];
  let next = 0;
  let done = 0;
  async function worker() {
    while (next < targets.length) {
      const ip = targets[next++];
      try {
        const found = await probe(ip);
        if (found) results.push(found);
      } catch { /* nothing there */ }
      done++;
      if (onProgress) onProgress(done, targets.length);
    }
  }
  await Promise.all(Array.from({ length: Math.min(concurrency, targets.length) }, worker));
  return results;
}

module.exports = {
  DEFAULT_PORT, newNonce, buildRequest, parseReply, localNetworks, broadcastTargets, discover, sweepTargets, sweep,
};
