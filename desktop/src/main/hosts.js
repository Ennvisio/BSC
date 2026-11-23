'use strict';
// Server addresses: what the user may type, and what the client will open.

const net = require('node:net');

const HOSTNAME = /^(?=.{1,253}$)[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/;

const PRIVATE_V4 = [
  ['10.0.0.0', 8], ['172.16.0.0', 12], ['192.168.0.0', 16], ['169.254.0.0', 16], ['127.0.0.0', 8],
];

function ipToInt(ip) {
  return ip.split('.').reduce((acc, part) => ((acc << 8) + Number(part)) >>> 0, 0);
}

function intToIp(n) {
  return [24, 16, 8, 0].map((shift) => (n >>> shift) & 255).join('.');
}

function inRange(ip, base, bits) {
  const mask = bits === 0 ? 0 : (~0 << (32 - bits)) >>> 0;
  return ((ipToInt(ip) & mask) >>> 0) === ((ipToInt(base) & mask) >>> 0);
}

/** True for addresses that can only be on the ship's own network. */
function isPrivateIPv4(ip) {
  return net.isIPv4(ip) && PRIVATE_V4.some(([base, bits]) => inRange(ip, base, bits));
}

function isValidHost(host) {
  if (typeof host !== 'string') return false;
  return net.isIPv4(host) || HOSTNAME.test(host);
}

/**
 * Turns what a user typed ("192.168.1.10", "master-pc", "https://master-pc/")
 * into a bare host name or IPv4 address, or null if it is not one. Ports are
 * not accepted here: the server's ports come from discovery or settings.
 */
function normaliseHost(input) {
  if (typeof input !== 'string') return null;
  let value = input.trim().toLowerCase();
  value = value.replace(/^https?:\/\//, '').replace(/\/.*$/, '');
  if (value.endsWith('.')) value = value.slice(0, -1);
  return isValidHost(value) ? value : null;
}

function validPort(port) {
  return Number.isInteger(port) && port > 0 && port < 65536;
}

function httpsOrigin(host, port = 443) {
  return port === 443 ? `https://${host}` : `https://${host}:${port}`;
}

function httpOrigin(host, port = 80) {
  return port === 80 ? `http://${host}` : `http://${host}:${port}`;
}

module.exports = { isPrivateIPv4, isValidHost, normaliseHost, validPort, httpsOrigin, httpOrigin, ipToInt, intToIp };
