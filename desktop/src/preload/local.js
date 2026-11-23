'use strict';
// Bridge for the client's own pages (choosing a server, connection lost).
// The NaviSuite pages from the server never get this bridge.

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('navisuite', {
  getSetup: () => ipcRenderer.invoke('local:get-setup'),
  discover: () => ipcRenderer.invoke('local:discover'),
  probe: (address) => ipcRenderer.invoke('local:probe', String(address)),
  connect: (id) => ipcRenderer.invoke('local:connect', String(id)),
  cancel: () => ipcRenderer.invoke('local:cancel'),
  retry: () => ipcRenderer.invoke('local:retry'),
  openSetup: () => ipcRenderer.invoke('local:open-setup'),
  getState: () => ipcRenderer.invoke('local:state'),
  onProgress: (callback) => ipcRenderer.on('local:progress', (_event, message) => callback(String(message))),
  onState: (callback) => ipcRenderer.on('local:state-changed', (_event, state) => callback(state)),
});
