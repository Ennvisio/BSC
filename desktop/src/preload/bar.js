'use strict';
// Bridge for the status bar. The bar is a local page; it can only receive the
// connection state and ask for a handful of named actions.

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('navisuite', {
  onState: (callback) => ipcRenderer.on('bar:state', (_event, state) => callback(state)),
  action: (name) => ipcRenderer.send('bar:action', String(name)),
  ready: () => ipcRenderer.send('bar:ready'),
});
