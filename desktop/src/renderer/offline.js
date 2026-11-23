'use strict';

const api = window.navisuite;
const $ = (id) => document.getElementById(id);
let state = null;

const TEXT = {
  offline: ['The NaviSuite server cannot be reached', 'Check that this PC is connected to the ship\'s network and that the server is switched on.'],
  untrusted: ['The server\'s identity could not be verified', 'The server presented a certificate that was not issued by this vessel\'s certificate authority. The server may have been reinstalled, or another computer may be pretending to be it. Do not sign in; ask the officer responsible for IT.'],
  'wrong-server': ['A different server answers at this address', 'The address now belongs to another vessel\'s server. Ask the officer responsible for IT, or choose the server again.'],
  down: ['The server reports a problem', 'The server answers but cannot work at the moment, for example because its database is not running.'],
  incompatible: ['This client and the server do not match', 'The server and this NaviSuite client are different versions that cannot work together.'],
};

function render() {
  const kind = (state && state.state) || new URLSearchParams(location.search).get('kind') || 'offline';
  const [title, detail] = TEXT[kind] || TEXT.offline;
  $('title').textContent = title;
  // For a version mismatch the monitor's own message says which side to update.
  $('detail').textContent = kind === 'incompatible' && state && state.detail ? state.detail : detail;
  $('icon').classList.toggle('warn', kind === 'down');
  const retrying = kind !== 'untrusted';
  $('retry-text').classList.toggle('hidden', !retrying);
  $('keep').classList.toggle('hidden', !retrying);
  if (retrying && state && state.nextCheckAt) {
    const seconds = Math.max(0, Math.ceil((state.nextCheckAt - Date.now()) / 1000));
    $('retry-text').lastChild.textContent = seconds > 0 ? `Trying again in ${seconds} s.` : 'Trying again...';
  }
  $('retry').classList.toggle('hidden', !retrying);
}

api.onState((s) => { state = s; render(); });
$('retry').addEventListener('click', () => api.retry());
$('change').addEventListener('click', () => api.openSetup());
(async () => {
  state = await api.getState();
  render();
  setInterval(render, 1000);
})();
