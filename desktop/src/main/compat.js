'use strict';
// Can this client work with that server? (Checkpoint 8)
//
// A vessel server publishes on /health:
//   "clients": { "api": 1, "min_version": "1.0.0" }
// api          what the server offers a client (/health, the CA download,
//              discovery, the sign-in flow). Raised only by a change that
//              breaks older clients (config/navisuite.php, compat).
// min_version  the oldest client release the server accepts.
//
// Servers from before Checkpoint 8 publish neither: they are api 1 and
// accept every client.

const SERVER_API = Object.freeze({ min: 1, max: 1 });

/** "1.2.3", "1.2.3-test.abc" -> [1, 2, 3]; anything else -> null. */
function parseVersion(text) {
  const m = /^(\d{1,5})\.(\d{1,5})\.(\d{1,5})(?:[-+][0-9A-Za-z.-]*)?$/.exec(String(text ?? '').trim());
  return m ? [Number(m[1]), Number(m[2]), Number(m[3])] : null;
}

/** -1, 0 or 1. Pre-release suffixes are ignored. Unreadable versions compare equal. */
function compareVersions(a, b) {
  const x = parseVersion(a);
  const y = parseVersion(b);
  if (!x || !y) return 0;
  for (let i = 0; i < 3; i++) {
    if (x[i] !== y[i]) return x[i] < y[i] ? -1 : 1;
  }
  return 0;
}

/**
 * @param {object} health        the server's /health answer
 * @param {string} clientVersion this client's version (app.getVersion())
 * @returns {{ok: true} | {ok: false, reason: 'client-too-old'|'server-too-old', message: string}}
 */
function checkCompatibility(health, clientVersion) {
  const clients = health && typeof health.clients === 'object' && health.clients !== null ? health.clients : {};
  const api = Number.isInteger(clients.api) ? clients.api : 1;
  const minVersion = typeof clients.min_version === 'string' && parseVersion(clients.min_version) ? clients.min_version : null;

  if (api > SERVER_API.max || (minVersion && compareVersions(clientVersion, minVersion) < 0)) {
    const needed = minVersion && compareVersions(clientVersion, minVersion) < 0 ? ` ${minVersion} or later` : ' a newer version';
    return {
      ok: false,
      reason: 'client-too-old',
      message: `This NaviSuite client (${clientVersion}) is too old for this server, which needs${needed}. Ask the officer responsible for IT to install the client that came with the server's installer.`,
    };
  }
  if (api < SERVER_API.min) {
    return {
      ok: false,
      reason: 'server-too-old',
      message: `This server runs an older NaviSuite release than this client (${clientVersion}) supports. Ask the officer responsible for IT to update the server.`,
    };
  }
  return { ok: true };
}

module.exports = { SERVER_API, parseVersion, compareVersions, checkCompatibility };
