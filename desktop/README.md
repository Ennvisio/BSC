# NaviSuite desktop client (Windows)

A thin Electron shell for crew PCs. It finds the vessel server on the LAN,
verifies its certificate against the vessel's own CA, and shows the existing
NaviSuite web pages from that server. It has no database and no backend.

Design, discovery protocol, security, IT settings and rollout:
[docs/checkpoint-4-desktop-client.md](../docs/checkpoint-4-desktop-client.md)

```
npm ci
npm start          # run from source (Change server is in the menu)
npm test           # unit and integration tests, Node.js 22 or later; uses openssl and php if present
npm run smoke      # the real program against a test server; needs a desktop session
npm run dist       # dist/NaviSuite-Client-Setup-<version>.exe
```

Release builds of both installers, with the test gate:
`deploy/build-installers.sh` ([docs/checkpoint-8-installers.md](../docs/checkpoint-8-installers.md)).
Installer options: `/S`, `/SERVER=host[,host2]`, `/FINGERPRINT=AB:CD:...`,
`/VESSEL=<id>`, `/LOCK`, `/ALLOWDOWNGRADE`; uninstall `/REMOVESETTINGS`.

| Folder | Contents |
|---|---|
| `src/main/` | Main process: window and views (`main.js`), discovery, server checks, certificate pinning, settings, reconnection |
| `src/preload/` | The small APIs the client's own pages may call |
| `src/renderer/` | The client's own pages: status bar, setup, offline |
| `build/` | Installer additions (`installer.nsh`) and the icon |
| `test/` | `node --test` suites, helpers that create a test vessel CA and server, and the smoke test |

Environment variables for development and tests: `NAVISUITE_CLIENT_DATA`
(settings folder), `NAVISUITE_MANAGED_CONFIG` (IT settings file) and
`NAVISUITE_SMOKE=1` (machine-readable events on stdout).

When run from an editor built on Electron, such as VS Code, unset
`ELECTRON_RUN_AS_NODE` before `npm start`, or Electron starts as plain Node.js.
