'use strict';

const $ = (id) => document.getElementById(id);
let current = null;

const LOOK = {
  setup: ['idle', 'No server chosen'],
  connecting: ['idle busy', 'Connecting...'],
  online: ['ok', 'Connected'],
  degraded: ['warn', 'Connected - server needs attention'],
  down: ['bad', 'Server problem'],
  offline: ['bad busy', 'Offline'],
  untrusted: ['bad', 'Server not verified'],
  'wrong-server': ['bad', 'Wrong server'],
  incompatible: ['bad', 'Client and server versions do not match'],
};

function render() {
  if (!current) return;
  const [cls, label] = LOOK[current.state] || LOOK.connecting;
  let text = label;
  if (current.state === 'offline' && current.nextCheckAt) {
    const seconds = Math.max(0, Math.ceil((current.nextCheckAt - Date.now()) / 1000));
    text = seconds > 0 ? `Offline - retrying in ${seconds} s` : 'Offline - retrying...';
  }
  $('status').className = `status ${cls}`;
  $('status-text').textContent = text;
  $('status').title = current.detail || label;

  const server = current.server;
  $('vessel').textContent = server ? (server.vesselName || `Vessel ${server.vesselId || ''}`.trim()) : 'NaviSuite';
  $('host').textContent = server ? server.host : '';
  const inApp = current.view === 'app';
  $('back').disabled = !current.canGoBack;
  $('forward').disabled = !current.canGoForward;
  $('reload').disabled = !server;
  $('home').disabled = !server;
  $('server').classList.toggle('hidden', Boolean(current.locked));
  document.title = inApp ? 'NaviSuite' : 'NaviSuite setup';
}

window.navisuite.onState((state) => { current = state; render(); });
for (const action of ['back', 'forward', 'reload', 'home', 'server', 'about']) {
  $(action).addEventListener('click', () => window.navisuite.action(action));
}
setInterval(render, 1000);
window.navisuite.ready();
