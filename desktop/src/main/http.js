'use strict';
// Small HTTP(S) client for talking to the vessel server from the main
// process: /health, the CA certificate. HTTPS requests trust only the CA
// passed in, never the operating system's list, and never follow redirects.

const http = require('node:http');
const https = require('node:https');

class HttpError extends Error {
  constructor(message, kind, code) {
    super(message);
    this.kind = kind; // 'network' | 'tls' | 'timeout' | 'too-large' | 'format'
    this.code = code;
  }
}

const TLS_CODES = new Set([
  'UNABLE_TO_VERIFY_LEAF_SIGNATURE', 'UNABLE_TO_GET_ISSUER_CERT', 'UNABLE_TO_GET_ISSUER_CERT_LOCALLY',
  'SELF_SIGNED_CERT_IN_CHAIN', 'DEPTH_ZERO_SELF_SIGNED_CERT', 'CERT_SIGNATURE_FAILURE', 'CERT_HAS_EXPIRED',
  'CERT_NOT_YET_VALID', 'ERR_TLS_CERT_ALTNAME_INVALID', 'CERT_UNTRUSTED', 'CERT_REJECTED', 'INVALID_CA',
]);

function classify(err) {
  if (err instanceof HttpError) return err;
  const code = err && err.code;
  if (TLS_CODES.has(code) || /certificate|tls|ssl/i.test(String(err && err.message))) {
    // Node appends advice for developers ("try running Node.js with ..."); drop it.
    const reason = String(err.message).replace(/;\s*if the root CA is installed locally.*$/is, '');
    return new HttpError(`Secure connection refused: ${reason}`, 'tls', code);
  }
  return new HttpError(err && err.message ? err.message : 'Network error', 'network', code);
}

/**
 * GET a URL. Resolves { status, headers, body } for any HTTP status.
 * Options: timeoutMs, maxBytes, ca (PEM; required for https).
 */
function get(url, { timeoutMs = 4000, maxBytes = 64 * 1024, ca } = {}) {
  return new Promise((resolve, reject) => {
    const target = new URL(url);
    const isHttps = target.protocol === 'https:';
    if (isHttps && !ca) {
      reject(new HttpError('No certificate authority given for HTTPS.', 'tls'));
      return;
    }
    const options = {
      method: 'GET',
      headers: { Accept: 'application/json, */*', 'User-Agent': 'NaviSuite-Client' },
      agent: false,
      timeout: timeoutMs,
    };
    if (isHttps) {
      options.ca = ca;
      options.rejectUnauthorized = true;
    }
    const req = (isHttps ? https : http).request(target, options, (res) => {
      const chunks = [];
      let size = 0;
      res.on('data', (chunk) => {
        size += chunk.length;
        if (size > maxBytes) {
          req.destroy(new HttpError('Response too large.', 'too-large'));
          return;
        }
        chunks.push(chunk);
      });
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: Buffer.concat(chunks) }));
      res.on('error', (err) => reject(classify(err)));
    });
    req.on('timeout', () => req.destroy(new HttpError('The server did not answer in time.', 'timeout', 'ETIMEDOUT')));
    req.on('error', (err) => reject(classify(err)));
    req.end();
  });
}

/** GET and parse JSON. Statuses other than 200 and 503 are errors. */
async function getJson(url, options) {
  const res = await get(url, options);
  if (res.status !== 200 && res.status !== 503) {
    throw new HttpError(`Unexpected HTTP status ${res.status}.`, 'format', res.status);
  }
  try {
    return { status: res.status, json: JSON.parse(res.body.toString('utf8')) };
  } catch {
    throw new HttpError('The answer is not JSON.', 'format');
  }
}

module.exports = { get, getJson, HttpError };
