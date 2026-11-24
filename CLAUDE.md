# CLAUDE.md: NaviSuite (BSC) project context

Read this first in every session. It replaces the memory notes kept on the
original development Mac. Details live in `docs/`; this file says where.

## What this project is

A Laravel 11 / PHP 8.2 application for a shipping company ("NaviSuite"):
vessel master data, certificates and surveys, inventory and stock ledger,
PMS (planned maintenance), and a multi-stage stores and service
**requisition** approval workflow from ship crew to two shore departments
(SRD and SSM). The cloud version runs on `main` and is live in production.

**The goal of branch `feature/offline-sync`:** let each ship run NaviSuite
on its own master PC, offline, with crew PCs on the ship's LAN, and sync
safely with the cloud when the link is up. Pieces:

- vessel server on Windows: Apache, PHP, MySQL, queue, scheduler,
  discovery and sync as Windows services (`deploy/windows/`, NSIS
  installer in `deploy/installer/server/`);
- desktop client for crew PCs: an Electron shell that finds the server on
  the LAN, pins its certificate and shows the web pages (`desktop/`);
- two-way sync engine: MySQL triggers write an outbox; the vessel starts
  every connection, pushes then pulls (`app/Sync/`, rules in
  `config/sync.php`).

## Read these, in this order

1. `docs/implementation-roadmap.md`: the plan, Checkpoints 9 to 24, and
   section 17 (progress, test results). The approved decisions table is
   section 1.
2. `docs/pending-decisions.md`: every decision (PD-01 to PD-74), what is
   decided and what is still open. Its summary table says what each item's
   status is.
3. `docs/architecture-decisions.md`: ADR-001 (operating model), ADR-003
   to ADR-006 (master data, user administration, backups, no vessel
   deletion).
4. `docs/e2e-test-plan.md`: the Windows acceptance test plan.
5. As needed: `docs/checkpoint-1` to `checkpoint-8-*.md` (what each built
   part does), `docs/disaster-recovery.md`, `deploy/windows/README.md`,
   `desktop/README.md`, `CODEBASE_OVERVIEW.md` (the original app's modules
   and approval workflow).

## Status (2026-11-25)

- Checkpoints 1 to 8 built (sync foundation, Windows server scripts,
  desktop client, sync engine, business sync, files, installers).
  Checkpoint 10 complete. Checkpoints 14 and 15 partly built.
- **Nothing has ever been run on Windows.** The next step is the Windows
  shakedown (Checkpoint 13) on the office LAN: build the installers, install
  the server on one PC and the client on two others, and work through
  `docs/e2e-test-plan.md`. If that works, the owner deploys this branch.
- `deploy/windows/runtime.lock.json` is empty: the runtimes have not been
  pinned yet (PD-16). That is done on a Windows PC with
  `deploy/windows/prepare-runtime.ps1` (see `docs/checkpoint-8-installers.md`
  section 1).
- Checkpoint 12 (local web assets, no CDNs) was done on another branch and
  is to be merged into this one, then re-verified.
- Last full PHP gate on the Mac: 648 passed, 0 failed, 0 skipped, MySQL
  tests on. Desktop `npm test`: 36 passed.
- An "early vessel pilot" plan was proposed (2026-11-25), not yet written
  into the roadmap. Mandatory blockers found by checking the code:
  CP11 cloud security not done (attachments open by id, `/register` open,
  `ServiceRequisitionApproval` has `$guarded = []`, SQL dumps still
  tracked); password hashes stored in `sync_row_bases` (PD-37 item 2);
  database backups have no checksum or test restore; `vessel_surveys` is
  in no sync module; PD-13 must be decided before `@requisitions` goes on.
- Decided but **not built**: PD-21 (own photo and signature on board),
  PD-17 (certificates and surveys vessel-managed), PD-59 A beyond
  refused-change flagging, PD-36 cascade review and MySQL tests.

## Rules from the owner (always in force)

- Work only on `feature/offline-sync`. Do not merge into `main`, deploy,
  or commit unless the owner asks. Never push or deploy to production.
- Do not modify existing database migrations; new storage only through new,
  additive migrations, named so the owner can refuse them.
- No destructive database commands, and never touch production data.
- Do not remove or replace existing functionality, and do not change
  existing business behaviour outside the approved scope. Preserve
  compatibility with the existing cloud application.
- Do not invent business rules or missing business decisions. Do not
  reopen decisions already approved. Ask only about genuine business
  decisions; settle technical details from the codebase.
- If a genuinely blocking decision arises, stop at that point, explain it,
  ask, and carry on with independent work.
- Never store passwords or credentials in plain text, and never show them
  in logs or ordinary sync records. Do not print the contents of `.env`.
- Do not upgrade Laravel (PD-32 deferred).
- Vessel users have no delete access of any kind (ADR-006).
- No attachment deletion and no new attachment-delete permission (PD-61).
- Do not deploy without a passing test suite.
- Verify the actual code and tests before stating a status; do not trust
  an old roadmap line, and do not mark something built because it was
  decided.
- Do real development when asked to build; documentation alone is not
  completion.

## How the owner wants reports

- Outcome first, then details. Tables for numbers and status.
- Plain English; no em-dashes or en-dashes.
- Say clearly what was verified, what was not run, and that nothing was
  committed (when true).
- Keep "Approved" (the owner's decision) and "Recommended" (Claude's
  proposal) apart everywhere.

## Working in this code

- `app/Http/Controllers/HomeController.php` is a very large controller
  (vessels, surveys, certificates, items, users, trash, part of orders).
- The approval workflow is an implicit state machine: nullable approver
  columns on `order_approvals` plus a free-text `orders.status`. Read the
  live (uncommented) branches of `RoleController` before changing it.
- Receipt and verification goes to the role that raised the requisition
  (`Order::canConfirmReceipt()`, `created_by_role`), not to the master.
- Deletion is mostly hand-rolled status flags (`status`, `ord_status`).
- Sync ownership comes only from `config/sync.php` (directions, column
  owners, `row_owner`, `owned_with`). `App\Sync\WrongSideEdits` enforces
  it in the app (model saves and query-builder writes);
  `App\Sync\Engine\ChangeApplier` enforces it at sync.
- Some files use CRLF line endings (for example `app/User.php`,
  `resources/views/profile.blade.php`); keep them as they are.
- Commands: `sync:status`, `sync:health`, `sync:conflicts`, `sync:run`,
  `sync:resend`, `sync:register-node`, `navisuite:vessel-offline`,
  `navisuite:status`.

## Tests

- PHP: `php artisan test` (SQLite in memory, partial schema builders in
  `tests/Concerns`).
- MySQL tests run only against a throwaway database whose name ends in
  `_test`: set `SYNC_MYSQL_TEST_DATABASE` and `SYNC_MYSQL_TEST_PORT` (and
  the other `SYNC_MYSQL_TEST_*` variables). Without them they skip, and a
  production installer build refuses skipped tests.
- Desktop: `cd desktop && npm ci && npm test`; `npm run smoke` needs a
  desktop session.
- Installers: `deploy/build-installers.sh --runtime <folder>` (a bash
  script, written for Mac or Linux; on Windows use Git Bash or WSL).
  `--test-build` skips the test gate and marks the installers as test
  builds: fine for the office LAN test, never for a vessel.
