'use strict';
// The vessel's certificate authority (CA) and the server certificates it
// issues. The client trusts exactly one CA per configured server: the one the
// user (or the administrator) confirmed. See docs/checkpoint-4-desktop-client.md.

const { X509Certificate } = require('node:crypto');
const net = require('node:net');

const MAX_CERT_BYTES = 16 * 1024;
const FINGERPRINT = /^([0-9A-F]{2}:){31}[0-9A-F]{2}$/i;

function toDate(cert, which) {
  const property = which === 'from' ? 'validFromDate' : 'validToDate';
  return cert[property] instanceof Date ? cert[property] : new Date(which === 'from' ? cert.validFrom : cert.validTo);
}

/**
 * Reads a CA certificate (PEM or DER). Throws unless it is a CA certificate
 * that is valid now. Returns { pem, sha256, subject, validTo }.
 */
function parseCaCertificate(data, now = new Date()) {
  const buffer = Buffer.isBuffer(data) ? data : Buffer.from(String(data));
  if (buffer.length === 0 || buffer.length > MAX_CERT_BYTES) throw new Error('The certificate file has an unexpected size.');
  let cert;
  try {
    cert = new X509Certificate(buffer);
  } catch {
    throw new Error('The file is not a certificate.');
  }
  if (!cert.ca) throw new Error('The certificate is not a certificate authority.');
  if (now < toDate(cert, 'from') || now > toDate(cert, 'to')) throw new Error('The certificate authority has expired or is not valid yet.');
  return { pem: cert.toString(), sha256: cert.fingerprint256, subject: cert.subject, validTo: toDate(cert, 'to').toISOString() };
}

function isFingerprint(value) {
  return typeof value === 'string' && FINGERPRINT.test(value);
}

function sameFingerprint(a, b) {
  return isFingerprint(a) && isFingerprint(b) && a.toUpperCase() === b.toUpperCase();
}

/**
 * Checks a server certificate against the pinned CA:
 * issued and signed by that CA, valid now, not itself a CA, and made out to
 * the host being opened. Returns { ok: true } or { ok: false, reason }.
 */
function verifyServerCertificate({ leafPem, caPem, host, now = new Date() }) {
  let leaf;
  let ca;
  try {
    leaf = new X509Certificate(leafPem);
    ca = new X509Certificate(caPem);
  } catch {
    return { ok: false, reason: 'unreadable certificate' };
  }
  if (!ca.ca) return { ok: false, reason: 'pinned certificate is not a CA' };
  if (leaf.ca) return { ok: false, reason: 'server presented a CA certificate' };
  if (!leaf.checkIssued(ca) || !leaf.verify(ca.publicKey)) return { ok: false, reason: 'not issued by this vessel\'s certificate authority' };
  if (now < toDate(leaf, 'from') || now > toDate(leaf, 'to')) return { ok: false, reason: 'server certificate expired or not yet valid' };
  if (now > toDate(ca, 'to')) return { ok: false, reason: 'certificate authority expired' };
  const match = net.isIPv4(host) ? leaf.checkIP(host) : leaf.checkHost(host);
  if (!match) return { ok: false, reason: `certificate is not made out to ${host}` };
  return { ok: true };
}

/** "AB:CD:..." split into lines of 8 pairs, for people to compare. */
function displayFingerprint(fp) {
  if (!isFingerprint(fp)) return '';
  const parts = fp.split(':');
  const lines = [];
  for (let i = 0; i < parts.length; i += 8) lines.push(parts.slice(i, i + 8).join(':'));
  return lines.join('\n');
}

module.exports = { parseCaCertificate, verifyServerCertificate, isFingerprint, sameFingerprint, displayFingerprint };
