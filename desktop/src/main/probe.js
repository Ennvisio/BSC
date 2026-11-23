'use strict';
// Identifying one server address: is it a NaviSuite vessel server, which
// vessel, and which certificate authority does it use?
//
// Everything learned over plain HTTP here is unauthenticated. It only becomes
// trusted when the CA fingerprint is confirmed by a person, matches the one
// the administrator configured, or the server is already trusted by Windows.

const { get, getJson, HttpError } = require('./http');
const { parseCaCertificate } = require('./trust');
const { httpOrigin, httpsOrigin } = require('./hosts');

class ProbeError extends Error {
  constructor(message, kind) {
    super(message);
    this.kind = kind; // 'unreachable' | 'not-navisuite' | 'no-certificate' | 'tls'
  }
}

/** Quick check used by the sweep: GET http://host/health. */
async function quickHealth(host, { httpPort = 80, timeoutMs = 800 } = {}) {
  try {
    const { json } = await getJson(`${httpOrigin(host, httpPort)}/health`, { timeoutMs, maxBytes: 8192 });
    if (json && json.app === 'NaviSuite' && json.mode === 'vessel') return json;
  } catch { /* not a NaviSuite server */ }
  return null;
}

/**
 * Full identification of host. Resolves:
 *   { host, httpsPort, httpPort, vesselId, vesselName, release, status,
 *     caPem, caSha256, health }   (health: the /health answer, for compat.js)
 * Throws ProbeError.
 */
async function identifyServer(host, { httpsPort = 443, httpPort = 80, timeoutMs = 4000 } = {}) {
  let summary;
  try {
    ({ json: summary } = await getJson(`${httpOrigin(host, httpPort)}/health`, { timeoutMs, maxBytes: 8192 }));
  } catch (err) {
    throw new ProbeError(`No answer from ${host}: ${err.message}`, 'unreachable');
  }
  if (!summary || summary.app !== 'NaviSuite' || summary.mode !== 'vessel') {
    throw new ProbeError(`${host} is not a NaviSuite vessel server.`, 'not-navisuite');
  }

  let ca;
  try {
    const res = await get(`${httpOrigin(host, httpPort)}/navisuite-ca.crt`, { timeoutMs, maxBytes: 16 * 1024 });
    if (res.status !== 200) throw new Error(`HTTP ${res.status}`);
    ca = parseCaCertificate(res.body);
  } catch (err) {
    throw new ProbeError(`Could not read the server's certificate authority: ${err.message}`, 'no-certificate');
  }

  // The server must prove it holds a certificate from that CA for this name.
  let secure;
  try {
    ({ json: secure } = await getJson(`${httpsOrigin(host, httpsPort)}/health`, { ca: ca.pem, timeoutMs, maxBytes: 8192 }));
  } catch (err) {
    const kind = err instanceof HttpError && err.kind === 'tls' ? 'tls' : 'unreachable';
    throw new ProbeError(`HTTPS check failed for ${host}: ${err.message}`, kind);
  }

  const vessel = (secure && secure.vessel) || summary.vessel || null;
  return {
    host,
    httpsPort,
    httpPort,
    vesselId: vessel && Number.isInteger(vessel.id) ? vessel.id : null,
    vesselName: vessel && typeof vessel.name === 'string' ? vessel.name : null,
    release: (secure && secure.release) || summary.release || null,
    status: (secure && secure.status) || summary.status || null,
    caPem: ca.pem,
    caSha256: ca.sha256,
    health: secure || summary,
  };
}

module.exports = { identifyServer, quickHealth, ProbeError };
