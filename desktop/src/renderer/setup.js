'use strict';

const api = window.navisuite;
const $ = (id) => document.getElementById(id);
let setup = null;
let selected = null;
let busy = false;

function el(tag, attrs = {}, children = []) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (k === 'text') node.textContent = v;
    else if (k === 'class') node.className = v;
    else node.setAttribute(k, v);
  }
  for (const child of children) node.append(child);
  return node;
}

function notice(text, bad = false) {
  $('notice').textContent = text;
  $('notice').className = `notice${bad ? ' bad' : ''}`;
}

function vesselLabel(s) {
  return s.vesselName || (s.vesselId ? `Vessel ${s.vesselId}` : 'Unknown vessel');
}

function verifiedText(s) {
  if (s.verifiedBy === 'managed') return 'matches the fingerprint set by your administrator';
  if (s.verifiedBy === 'windows') return 'its certificate is trusted by Windows on this PC';
  return null;
}

function renderList(servers) {
  const list = $('list');
  list.replaceChildren();
  for (const s of servers) {
    const meta = [s.hosts.join(', '), s.release ? `release ${s.release}` : null].filter(Boolean).join('  |  ');
    const name = el('div', { class: 'name', text: vesselLabel(s) });
    if (s.verifiedBy) name.append(el('span', { class: 'badge', text: 'Verified' }));
    const button = el('button', { text: 'Select' });
    button.addEventListener('click', () => showConfirm(s));
    list.append(el('li', {}, [el('div', { class: 'info' }, [name, el('div', { class: 'meta', text: meta })]), button]));
  }
}

async function search() {
  if (busy) return;
  busy = true;
  $('search').disabled = true;
  $('list').replaceChildren();
  $('progress').replaceChildren(el('span', { class: 'spinner' }), 'Searching the network...');
  const result = await api.discover();
  busy = false;
  $('search').disabled = false;

  if (result.error) {
    $('progress').textContent = result.error;
    return;
  }
  const servers = result.servers || [];
  renderList(servers);
  if (servers.length === 0) {
    $('progress').textContent = setup.locked
      ? 'The server set by your administrator does not answer. Check that it is switched on and connected, then search again.'
      : 'No NaviSuite server answered. Check the network cable, or enter the address below.';
    return;
  }
  $('progress').textContent = servers.length === 1 ? 'One server found.' : `${servers.length} servers found. Choose your vessel's server.`;

  // Set by the administrator and matching: no question to ask.
  const managed = servers.find((s) => s.verifiedBy === 'managed');
  if (managed) connect(managed);
}

function showConfirm(s) {
  selected = s;
  $('c-vessel').textContent = vesselLabel(s);
  $('c-address').textContent = s.hosts.join(', ');
  $('c-release').textContent = s.release || 'unknown';
  $('c-fingerprint').textContent = s.fingerprint;
  $('c-error').textContent = '';
  const verified = verifiedText(s);
  const changed = setup.current && setup.current.fingerprint && setup.current.fingerprint !== s.fingerprint;
  let text;
  if (verified) {
    text = `Verified: ${verified}.`;
    $('c-connect').textContent = 'Connect';
  } else {
    text = 'Compare this fingerprint with the one on the server: it is shown at the end of the server installation and by check-health.ps1 on the master PC, or ask the officer responsible for IT. Connect only if every character matches. Someone else on the network could otherwise pretend to be the server.';
    $('c-connect').textContent = 'The fingerprints match - connect';
  }
  if (changed) text += ' Note: this is not the server this PC used before.';
  $('c-explain').textContent = text;
  $('confirm').classList.remove('hidden');
  $('found').classList.add('hidden');
  $('manual').classList.add('hidden');
  $('c-connect').focus();
}

function hideConfirm() {
  selected = null;
  $('confirm').classList.add('hidden');
  $('found').classList.remove('hidden');
  if (!setup.locked) $('manual').classList.remove('hidden');
}

async function connect(s) {
  $('c-connect').disabled = true;
  const result = await api.connect(s.id);
  $('c-connect').disabled = false;
  if (result && result.error) {
    if ($('confirm').classList.contains('hidden')) showConfirm(s);
    $('c-error').textContent = result.error;
  }
}

$('search').addEventListener('click', search);
$('c-connect').addEventListener('click', () => { if (selected) connect(selected); });
$('c-cancel').addEventListener('click', hideConfirm);
$('back-to-app').addEventListener('click', () => api.cancel());
$('manual-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const address = $('address').value.trim();
  if (!address) return;
  $('manual-error').textContent = '';
  $('check').disabled = true;
  $('check').textContent = 'Checking...';
  const result = await api.probe(address);
  $('check').disabled = false;
  $('check').textContent = 'Check';
  if (result.error) {
    $('manual-error').textContent = result.error;
  } else {
    showConfirm(result.server);
  }
});
api.onProgress((message) => {
  $('progress').replaceChildren(el('span', { class: 'spinner' }), message);
});

(async () => {
  setup = await api.getSetup();
  $('version').textContent = `NaviSuite client ${setup.version}`;
  const reason = new URLSearchParams(location.search).get('reason');
  if (setup.managedError) notice(`The administrator settings on this PC could not be used: ${setup.managedError}`, true);
  else if (reason === 'settings' || setup.settingsError) notice('The saved server settings could not be read, so the server must be chosen again.');
  if (setup.locked) $('manual').classList.add('hidden');
  if (setup.current) $('back-to-app').classList.remove('hidden');
  search();
})();
