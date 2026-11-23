# Checkpoint 1: Cloud and Vessel Modes

**Status:** Implemented on branch `feature/offline-sync`, awaiting review. Synchronisation is not part of this checkpoint.
**Date:** 2026-11-20
**Plan:** [offline-implementation-roadmap.md](offline-implementation-roadmap.md), checkpoint 1. Risk and question numbers (R1, Q2 and so on) refer to [offline-architecture-audit.md](offline-architecture-audit.md).

---

## 1. What this checkpoint delivers

The same codebase now runs either as the **cloud** server or as one **vessel's** onboard server, chosen by configuration.

- **Cloud is the default.** A server without `APP_MODE` behaves exactly as before. The one new cloud behaviour is switched off for every vessel until an administrator turns it on.
- **Vessel mode** limits sign-in to the crew of one vessel and makes office-maintained data read-only on board. It uses its own database connection.
- **A status command** reports the mode, the services that differ between the two environments, and any misconfiguration.
- **A cloud write lock** can be turned on per vessel once that vessel has its own server, so each vessel's data has a single author (R1).

No existing controller, model, view or migration was edited. The rules sit in one middleware appended to the `web` group.

## 2. Configuration

| Variable | Mode | Default | Meaning |
|---|---|---|---|
| `APP_MODE` | both | `cloud` | `cloud` or `vessel`. Case and spaces are ignored. Any other value stops the server from serving requests (HTTP 503) rather than guessing. |
| `APP_VESSEL_ID` | vessel | none | The vessel's `vessels.id` in the cloud database. Required in vessel mode. |
| `APP_SUPPORT_ACCOUNTS` | vessel | empty | Comma-separated e-mails of shore accounts that may sign in on board for support. They are read-only. |
| `APP_LOCK_OFFLINE_VESSELS` | cloud | `true` | Master switch for the cloud write lock (section 3.2). |
| `DB_CONNECTION` | both | `mysql`, or `vessel` when `APP_MODE=vessel` | An explicit value always wins, so existing cloud `.env` files are unaffected. |
| `VESSEL_DB_HOST`, `_PORT`, `_DATABASE`, `_USERNAME`, `_PASSWORD`, `_SOCKET` | vessel | `127.0.0.1`, `3306`, `navisuite`, `navisuite`, empty, empty | The onboard MySQL server. These are separate variables, so a vessel server can never pick up cloud credentials by accident. |

The settings live in [config/navisuite.php](../config/navisuite.php) and are read only through `App\Support\AppMode`. The blocked-path lists are in the same file, so they can be reviewed in one place.

Example files:

- [.env.example](../.env.example) is for the cloud. It is new, because the repository had none.
- [.env.vessel.example](../.env.vessel.example) is for a vessel's master PC.

## 3. Behaviour by mode

### 3.1 Vessel mode

| Area | Behaviour |
|---|---|
| Sign-in | Only users whose role is a ship role (`user_type = ship`) on this vessel, plus listed support accounts. Everyone else gets "This account cannot sign in on this vessel's server". The check runs after the password is verified and before a session is created. |
| Existing sessions | A signed-in user who does not belong here, for example through a remember-me cookie, is signed out on their next request. |
| Office-maintained data | Writes are refused with HTTP 403: users, vessel particulars and status, requisition categories, PMS master data, trash restore and permanent delete, and procurement completion. Viewing those pages still works. |
| Registration and password reset | Unavailable. Accounts come from the office, and reset links need e-mail. |
| Support accounts | Can view everything their role allows and can sign out. Every other write is refused. |
| Crew work | Unchanged. Requisitions, stock, PMS, equipment, certificates and surveys, budget groups, certificate categories, catalog items, attachments, profile and password all work as on the cloud. |
| Misconfiguration | With no valid `APP_VESSEL_ID`, every request returns HTTP 503 and the problem is written to the log. |

Blocked writes return JSON `{"message": ...}` with status 403 to AJAX calls, and a 403 page with the same message to normal form posts.

### 3.2 Cloud mode

| Area | Behaviour |
|---|---|
| Everything | Unchanged. All users sign in as before. Registration and password reset are unchanged; disabling registration is a Checkpoint 0 decision (R14). |
| Offline-enabled vessel | Once `vessels.offline_enabled_at` is set, that vessel's ship crew can still sign in, view, search and sign out on the cloud, but cannot change anything. Other vessels and all shore users are unaffected. |
| Before the migration runs | The lock check treats a missing column as "not offline", so deploying the code before running the migration is safe. |

## 4. Environment-specific services

What differs between the cloud and a vessel server, and when each difference is handled. `php artisan navisuite:status` prints the live values.

| Service | Cloud | Vessel | Handled in |
|---|---|---|---|
| Database | Hosted MySQL, `mysql` connection | Local MySQL on `127.0.0.1`, `vessel` connection | **This checkpoint** |
| Who can sign in | Everyone | This vessel's crew and support accounts | **This checkpoint** |
| Office-maintained data | Editable | Read-only | **This checkpoint** |
| Mail | SMTP. Its only use is password-reset links. | `log` driver. Reset is disabled. | **This checkpoint** (example `.env`, status warning) |
| Debug output | Per server | Must be off, because the LAN is shared | **This checkpoint** (status warning) |
| Logging | `stack` | `daily`, so log files rotate on a PC nobody watches | **This checkpoint** (example `.env`) |
| Session cookie | Per server | `SESSION_SECURE_COOKIE=true`, since the LAN uses HTTPS | **This checkpoint** (example `.env`) |
| Cache and session store | File | File | No change needed |
| Queue | `sync` | `sync` for now, `database` once sync jobs exist | Checkpoint 2 |
| Scheduler (`pms:refresh-jobs`) | All vessels | This vessel | Checkpoint 2. The cloud must skip offline-enabled vessels (roadmap 4.3, item 5). |
| Front-end libraries | Public CDNs | Must be served locally | **Checkpoint 0, still open.** Pages will not render properly offline until this is done. |
| File storage | `storage/app` and `images/` under the web root | Same paths, under `C:\NaviSuite\data` | Checkpoint 9 (installer) |
| Outbound network | None. The app makes no HTTP calls and sends no mail apart from reset links. | None | Nothing to do |
| Broadcasting | Defined, not used | Not used | Nothing to do |
| Sync API and change capture | Not built | Not built | Checkpoints 2 to 7 |

## 5. Setting up a vessel server by hand

These steps are for testing until the installer exists (Checkpoint 9).

1. Install PHP 8.3 and MySQL. Bind MySQL to `127.0.0.1`.
2. Create the `navisuite` database and user. Load a copy of the cloud database into it.
3. Copy `.env.vessel.example` to `.env`. Set `APP_VESSEL_ID` and `VESSEL_DB_PASSWORD`, then run `php artisan key:generate`.
4. Run `php artisan migrate` to add the new column.
5. Run `php artisan navisuite:status`. It must end with "configured for vessel mode" and exit with code 0.

A full cloud copy contains every vessel's data. Crew can see only their own vessel through the app's existing vessel scoping, but the database file holds everything. Checkpoint 3 replaces this with a vessel-only export.

## 6. Commands

```
php artisan navisuite:status [--skip-db]
```
This command is read-only. It prints the mode, the vessel, the database connection and the environment-specific drivers, then any warnings and errors. It exits with code 1 when there are errors, so an installer or a monitoring check can call it.

```
php artisan navisuite:vessel-offline {vessel-id} [--disable] [--force]
```
This command runs on the cloud only. It sets or clears `vessels.offline_enabled_at` for one vessel and asks for confirmation unless `--force` is given. **Do not run it in production until sync is live.** Until then, the crew's onboard work would not reach the office, and the crew could no longer make changes on the cloud.

## 7. Database impact

| Change | Detail |
|---|---|
| New migration | `2026_11_20_100000_add_offline_enabled_at_to_vessels_table`. It adds `vessels.offline_enabled_at TIMESTAMP NULL` and changes no data. It is idempotent, and its `down()` removes the column. |
| Existing migrations | None edited |
| Existing data | None changed. Every vessel stays `NULL`, which means "not offline". |
| New connection | `vessel` in `config/database.php`. It is configuration only. No database was created. |
| Runtime queries | On the cloud, each write by a ship-crew user adds two small queries: a column-existence check and a lookup on `vessels`. Other requests run no extra queries. |

The migration has not been run against any real database. It ran only in the in-memory SQLite test database.

## 8. Files

**New:**
- `config/navisuite.php`
- `app/Support/AppMode.php`
- `app/Http/Middleware/EnforceAppMode.php`
- `app/Providers/AppModeServiceProvider.php`
- `app/Console/Commands/NaviSuiteStatus.php`
- `app/Console/Commands/NaviSuiteVesselOffline.php`
- `database/migrations/2026_11_20_100000_add_offline_enabled_at_to_vessels_table.php`
- `.env.example` and `.env.vessel.example`
- Tests: `tests/Unit/AppModeTest.php` and `tests/Feature/AppMode/` (`VesselModeTest`, `CloudModeTest`, `AppModeCommandsTest`, `BuildsAppModeFixtures`)
- This document

**Modified:**
- `bootstrap/app.php`: registers the middleware on the `web` group, ahead of route-model binding.
- `bootstrap/providers.php`: registers the new provider.
- `config/database.php`: adds the `vessel` connection and the mode-aware default.
- `phpunit.xml`: pins tests to cloud mode, so a developer's own `.env` cannot change the results.
- `docs/offline-implementation-roadmap.md`: uses the `APP_MODE` names and updates the status line.

## 9. Tests

There are 63 new tests: 8 unit tests and 55 feature tests. All pass. They cover:

- Mode parsing, including the cloud default, case and spaces, and that an unknown mode is an error.
- Vessel id validation and support-account parsing.
- The database default for each combination of `APP_MODE` and `DB_CONNECTION`.
- Vessel sign-in for this vessel's crew, another vessel's crew, shore roles, a shore role wrongly linked to this vessel, users with no role, wrong passwords and support accounts.
- Ending stale sessions.
- All 15 blocked office-maintained write paths.
- Registration and password reset being blocked on board.
- The read-only support account.
- 503 responses when the configuration is wrong.
- Cloud sign-in for all user types, and the unchanged registration and reset pages.
- The cloud write lock: on and off, other vessels, shore users, before the migration, and the read-only POST routes.
- Both commands and the migration's up, repeat-up and down.

The full suite has 249 tests: 240 pass and 9 fail. The same 9 tests fail on the previous commit without these changes. They fail because the sidebar counts query tables the partial test schemas do not build (R12, Checkpoint 0).

## 10. Open issues

1. **The checkpoint's exit criterion is not yet demonstrable.** "Runs fully offline on a Windows PC" needs the Checkpoint 0 work, in particular local copies of the CDN libraries. It also needs a Windows test machine. Everything here was tested on macOS with PHP 8.3.
2. **Some data the audit classed as office-owned is written by crew today.** Crew can maintain budget groups, certificate categories, catalog items, and their own photo and signature. These stay writable on board so no ship workflow is lost. Checkpoint 2 must therefore treat them as co-authored (class B), not cloud-owned (class A).
3. **Office edits to an offline vessel's data are not locked on the cloud yet.** Only that vessel's crew is locked. Locking office edits (Q2) means working out which vessel each record belongs to, which fits Checkpoint 2's table registry.
4. **The cloud scheduler still refreshes PMS jobs for offline-enabled vessels.** This is harmless until sync exists, and is due before Checkpoint 5.
5. **There is no on-screen indicator of vessel mode.** Buttons for blocked actions are still visible, mainly to support accounts, and clicking one shows the 403 message. A small banner and button hiding can be added with the sync status page (Checkpoint 8).
6. **Password reset on board.** Crew who forget a password need the office to reset it, and the change arrives by sync. Q4 is still open.
7. **Still outstanding from Checkpoint 0.** The cloud exposure check (R10), the 9 failing tests, the CDN libraries, and the decision on public registration.
