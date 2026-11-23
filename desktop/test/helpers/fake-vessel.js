'use strict';
// A stand-in for the vessel server: HTTP /health and /navisuite-ca.crt, and
// HTTPS /health and pages, with a certificate from a test vessel CA.

const http = require('node:http');
const https = require('node:https');

function startFakeVessel({ pki, httpsPki = pki, vessel = { id: 12, name: 'MV Test' }, httpPort = 0, httpsPort = 0 }) {
  // clients: what /health says about client versions (Checkpoint 8); null
  // leaves it out, as servers from before Checkpoint 8 do.
  const state = { status: 'ok', vessel, clients: null, requests: 0, httpsRequests: 0 };
  const health = () => JSON.stringify({
    status: state.status, app: 'NaviSuite', mode: 'vessel', vessel: state.vessel, release: 'test-release',
    ...(state.clients ? { clients: state.clients } : {}), time: new Date().toISOString(),
  });

  const httpServer = http.createServer((req, res) => {
    state.requests++;
    if (req.url === '/health') {
      res.writeHead(state.status === 'down' ? 503 : 200, { 'Content-Type': 'application/json' });
      res.end(health());
    } else if (req.url === '/navisuite-ca.crt') {
      res.writeHead(200, { 'Content-Type': 'application/x-x509-ca-cert' });
      res.end(pki.caPem);
    } else {
      res.writeHead(301, { Location: `https://${req.headers.host}${req.url}` });
      res.end();
    }
  });

  const handler = (req, res) => {
    state.requests++;
    state.httpsRequests++;
    if (req.url === '/health') {
      res.writeHead(state.status === 'down' ? 503 : 200, { 'Content-Type': 'application/json' });
      res.end(health());
      return;
    }
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end(`<!doctype html><title>NaviSuite test page</title><h1>Signed out</h1><p>${req.url}</p>`);
  };
  let httpsServer = https.createServer({ key: httpsPki.serverKey, cert: httpsPki.serverPem }, handler);

  const listen = (server, port) => new Promise((resolve) => server.listen(port, '127.0.0.1', () => resolve(server.address().port)));
  const close = (server) => new Promise((resolve) => { server.closeAllConnections?.(); server.close(() => resolve()); });

  return (async () => {
    const ports = { http: await listen(httpServer, httpPort), https: await listen(httpsServer, httpsPort) };
    return {
      ports,
      state,
      async stopHttps() { await close(httpsServer); },
      async startHttps(certPki = httpsPki) {
        httpsServer = https.createServer({ key: certPki.serverKey, cert: certPki.serverPem }, handler);
        await listen(httpsServer, ports.https);
      },
      async close() {
        await Promise.all([close(httpServer), httpsServer.listening ? close(httpsServer) : Promise.resolve()]);
      },
    };
  })();
}

module.exports = { startFakeVessel };
