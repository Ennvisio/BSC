# NaviSuite vessel server for Windows

Scripts that install and run NaviSuite on a ship's master PC: Apache with
PHP, MySQL on 127.0.0.1 only, a queue worker, a scheduler, the LAN
discovery responder (UDP 47810, for the desktop clients) and the sync
worker (NaviSuite-Sync, outbound HTTPS to the cloud only), all as Windows
services that start with Windows and restart after a failure.

Full procedure, firewall rules, security and troubleshooting:
[docs/checkpoint-3-windows-vessel-server.md](../../docs/checkpoint-3-windows-vessel-server.md)
Desktop clients and the discovery protocol:
[docs/checkpoint-4-desktop-client.md](../../docs/checkpoint-4-desktop-client.md)
Sync with the cloud (protocol, recovery, commands):
[docs/checkpoint-5-sync-engine.md](../../docs/checkpoint-5-sync-engine.md)

Since Checkpoint 8 the usual way is the installer, `NaviSuite-Server-Setup.exe`
(built by `deploy/build-installers.sh`), which runs `setup-server.ps1`:
[docs/checkpoint-8-installers.md](../../docs/checkpoint-8-installers.md).
Acceptance tests: [docs/e2e-test-plan.md](../../docs/e2e-test-plan.md).

| Script | Use |
|---|---|
| `setup-server.ps1` | What the installer runs: compatibility checks, then install, upgrade, repair or reinstall, runtime updates, the client kit, a full log |
| `install-server.ps1` | First install, or repair. Safe to run again. |
| `update-server.ps1` | Install a new release (`-ReleasePath`) or go back (`-Rollback`) |
| `check-health.ps1` | Check services, network exposure, certificate and `/health` |
| `configure-firewall.ps1` | Windows Firewall rules (group "NaviSuite") |
| `new-certificate.ps1` | Create or renew the HTTPS certificate |
| `backup-database.ps1` | Back up, or `-Restore`, the database |
| `backup-files.ps1` | Back up, check, export or `-Restore` the uploaded files |
| `maintenance.ps1` | Run daily by the "NaviSuite Maintenance" task |
| `startup-check.ps1` | Run 4 minutes after every boot by the "NaviSuite Startup Check" task |
| `prepare-runtime.ps1` | Build PC only: checks the pinned runtime downloads and builds the runtime folder for the installer |
| `uninstall-server.ps1` | Remove the services; `-RemoveData` deletes everything |

Run them in Windows PowerShell 5.1 or later, as administrator:

```
powershell -ExecutionPolicy Bypass -File .\deploy\windows\install-server.ps1 -VesselId 12 -RuntimeSource D:\navisuite-runtime
```

To sync with the cloud, add `-SyncNodeId`, `-SyncNodeToken` (from
`sync:register-node` on the cloud) and `-SyncCloudUrl https://...`, and put
`cacert.pem` (https://curl.se/ca/cacert.pem) in the runtime folder.

`templates\` holds the configuration files the scripts render. Edit the
templates, not the generated files in `C:\NaviSuite\config`, which are
written again on every install and update. Templates must stay ASCII, and
the scripts write every file as UTF-8 without a byte-order mark.

Never run `php artisan serve` or `php artisan queue:restart` on a vessel
server. Restart the Windows service instead.
