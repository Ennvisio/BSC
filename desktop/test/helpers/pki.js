'use strict';
// Test certificates, made exactly like deploy/windows/new-certificate.ps1
// does on the vessel server: same openssl.cnf template, same commands.

const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const TEMPLATE = path.join(__dirname, '..', '..', '..', 'deploy', 'windows', 'templates', 'openssl.cnf');

function hasOpenssl() {
  try {
    execFileSync('openssl', ['version'], { stdio: 'ignore' });
    return true;
  } catch {
    return false;
  }
}

function render(file, commonName, altNames) {
  const text = fs.readFileSync(TEMPLATE, 'utf8').replace('{{COMMON_NAME}}', commonName).replace('{{ALT_NAMES}}', altNames.join('\n'));
  fs.writeFileSync(file, text);
}

/** A vessel CA and a server certificate for the given names and IPs. */
function makeVesselPki({ name = 'test-master', dns = ['test-master', 'localhost'], ips = ['127.0.0.1'] } = {}) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'navisuite-pki-'));
  process.once('exit', () => fs.rmSync(dir, { recursive: true, force: true }));
  const f = (n) => path.join(dir, n);
  const run = (args) => execFileSync('openssl', args, { stdio: 'pipe' });
  render(f('ca.cnf'), `NaviSuite Vessel CA (${name})`, [`DNS.1 = ${name}`]);
  run(['req', '-x509', '-new', '-newkey', 'rsa:2048', '-nodes', '-sha256', '-days', '3650', '-config', f('ca.cnf'), '-extensions', 'ca_ext', '-keyout', f('ca.key'), '-out', f('ca.crt')]);
  const alt = [...dns.map((d, i) => `DNS.${i + 1} = ${d}`), ...ips.map((ip, i) => `IP.${i + 1} = ${ip}`)];
  render(f('server.cnf'), name, alt);
  run(['req', '-new', '-newkey', 'rsa:2048', '-nodes', '-sha256', '-config', f('server.cnf'), '-keyout', f('server.key'), '-out', f('server.csr')]);
  run(['x509', '-req', '-in', f('server.csr'), '-CA', f('ca.crt'), '-CAkey', f('ca.key'), '-CAserial', f('ca.srl'), '-CAcreateserial', '-days', '825', '-sha256', '-extfile', f('server.cnf'), '-extensions', 'server_ext', '-out', f('server.crt')]);
  const read = (n) => fs.readFileSync(f(n), 'utf8');
  return { dir, caPem: read('ca.crt'), serverPem: read('server.crt'), serverKey: read('server.key') };
}

module.exports = { hasOpenssl, makeVesselPki };
