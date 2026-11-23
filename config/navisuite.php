<?php

/*
|--------------------------------------------------------------------------
| NaviSuite deployment mode
|--------------------------------------------------------------------------
|
| The same codebase runs in two places:
|
|   cloud  - the shore server every office user and every vessel used until
|            now. This is the default, so a server without APP_MODE behaves
|            exactly as it always has.
|   vessel - the master PC on board one ship. It serves that ship's crew over
|            the LAN, keeps its own MySQL database and works without internet.
|
| Read these values through App\Support\AppMode rather than config() so the
| normalisation and validation rules live in one place.
|
| See docs/checkpoint-1-app-mode.md for the full list of what each mode
| changes, and docs/offline-implementation-roadmap.md for the wider plan.
|
*/

return [

    'mode' => env('APP_MODE', 'cloud'),

    /*
    | The vessels.id this server belongs to. Required in vessel mode and
    | ignored in cloud mode. It must match the id the vessel has in the cloud
    | database, because sync (a later checkpoint) relies on it.
    */
    'vessel_id' => env('APP_VESSEL_ID'),

    /*
    | Comma-separated e-mail addresses of shore accounts that may still sign
    | in on a vessel server, for remote support. They are read-only there.
    */
    'support_accounts' => env('APP_SUPPORT_ACCOUNTS', ''),

    /*
    |----------------------------------------------------------------------
    | Vessel-mode restrictions
    |----------------------------------------------------------------------
    |
    | Patterns are request paths as matched by Request::is(), because many of
    | this app's routes have no name. Only the crew of this vessel can sign
    | in on a vessel server, so shore-only actions are already out of reach.
    | These lists are the second line of defence: data the office maintains
    | must not be changed on board, or the two copies would drift apart.
    |
    */
    'vessel' => [

        // Blocked for every HTTP method.
        'blocked_paths' => [
            'register',      // accounts are created in the office and synced down
            'password/*',    // reset links need e-mail, which is unavailable offline
        ],

        // Blocked for POST, PUT, PATCH and DELETE. Viewing stays allowed.
        'blocked_write_paths' => [
            // Users and roles: maintained by the office (audit class A').
            'user/store', 'user/update', 'user/delete',
            // Vessel particulars: maintained by the office (audit class A').
            'vessel/store/*', 'vessel/delete', 'vessel/toggle-status',
            // Requisition categories and PMS master data: office reference data (class A).
            'category/store', 'category/update', 'category/delete',
            'pms/master/*',
            // Super-admin trash: restores and hard-deletes fleet records.
            'restore', 'permanent-delete',
            // Procurement is done by the office (SSM) only.
            'procurement/*/complete', 'service-procurement/*/complete',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | POST routes that only read data
    |----------------------------------------------------------------------
    |
    | A few screens search with POST. These stay available to read-only
    | users: support accounts on a vessel server, and ship crew whose vessel
    | has its own server (see 'cloud.lock_offline_vessels').
    |
    */
    'read_only_write_paths' => [
        'login', 'logout', 'search/*', 'broadcasting/auth',
    ],

    /*
    |----------------------------------------------------------------------
    | Cloud-mode restrictions
    |----------------------------------------------------------------------
    |
    | Once vessels.offline_enabled_at is set for a vessel, that vessel's ship
    | crew can still sign in to the cloud, but only to view. Their changes
    | belong on the onboard server, so the vessel has a single author (audit
    | risk R1). No vessel has the flag until an administrator runs
    | `php artisan navisuite:vessel-offline {id}`, so the cloud's behaviour
    | is unchanged until then.
    |
    */
    'cloud' => [
        'lock_offline_vessels' => env('APP_LOCK_OFFLINE_VESSELS', true),
    ],

    /*
    |----------------------------------------------------------------------
    | Health check: GET /health
    |----------------------------------------------------------------------
    |
    | Used by the desktop clients, the install scripts and monitoring. Anyone
    | on the LAN gets the overall status and which vessel this server is. The
    | individual checks are shown only to the server itself, to the listed
    | addresses, or to a caller sending the token in an X-Health-Token header.
    |
    */
    'health' => [
        // null: on for vessel servers, off on the cloud (404) unless set to true.
        'enabled' => env('APP_HEALTH_ENABLED'),
        'token' => env('APP_HEALTH_TOKEN'),
        // Comma-separated IPs or CIDR ranges, e.g. 192.168.10.0/24.
        'detail_ips' => env('APP_HEALTH_DETAIL_IPS', ''),
        'scheduler_max_age' => 180, // seconds
        'queue_max_age' => 300,     // seconds
        'discovery_max_age' => 180, // seconds
        'sync_max_age' => 180,      // seconds
        'min_free_disk_mb' => 2048,
        'required_extensions' => [
            'ctype', 'curl', 'dom', 'fileinfo', 'gd', 'mbstring', 'openssl',
            'pdo_mysql', 'tokenizer', 'xml', 'zip',
        ],
    ],

    // Shown by /health, so support can see which build a vessel runs.
    // The update script sets it.
    'release' => env('APP_RELEASE'),

    /*
    |----------------------------------------------------------------------
    | Desktop client compatibility (Checkpoint 8)
    |----------------------------------------------------------------------
    |
    | Published on /health, so a desktop client can tell before it opens the
    | server whether it can work with it. client_api is the version of what
    | the server offers the client (/health, /navisuite-ca.crt, discovery,
    | the web interface's sign-in flow). Raise it only when a change breaks
    | older clients; desktop/src/main/compat.js lists the versions a client
    | understands. min_client_version refuses clients older than a release
    | known to be needed.
    |
    */
    'compat' => [
        'client_api' => 1,
        'min_client_version' => env('APP_MIN_CLIENT_VERSION', '1.0.0'),
    ],

    /*
    |----------------------------------------------------------------------
    | LAN discovery: php artisan navisuite:discovery
    |----------------------------------------------------------------------
    |
    | The desktop clients find the vessel server by broadcasting a small UDP
    | question on the LAN; this server answers with its vessel, host name and
    | certificate fingerprint. The answer is a hint only: clients still check
    | the server's certificate before they trust it. The protocol and its
    | security are described in docs/checkpoint-4-desktop-client.md.
    |
    */
    'discovery' => [
        // null: on for vessel servers. Never runs on the cloud.
        'enabled' => env('APP_DISCOVERY_ENABLED'),
        'port' => (int) env('APP_DISCOVERY_PORT', 47810),
        'bind' => env('APP_DISCOVERY_BIND', '0.0.0.0'),
        // Answers per sender per 10 seconds, and for all senders per second.
        'max_replies_per_peer' => 5,
        'max_replies_per_second' => 20,
    ],

    /*
    | The vessel's public CA certificate (config\certs\ca.crt on the master
    | PC). Its SHA-256 fingerprint is announced by discovery, so a client can
    | show the crew what to compare. The installer sets the path.
    */
    'tls' => [
        'ca_certificate' => env('APP_TLS_CA_FILE'),
    ],

    /*
    | Sign-in (Checkpoint 7). Deactivated users (users.status false) cannot
    | sign in, and are signed out if they still have a session. On a vessel
    | server this works without the cloud: the flag arrives with the users
    | table. Set APP_BLOCK_DEACTIVATED_USERS=false only to restore the old
    | behaviour, where the flag was ignored at sign-in.
    */
    'auth' => [
        'block_deactivated' => env('APP_BLOCK_DEACTIVATED_USERS', true),
    ],

    /*
    | Credentials kept encrypted on disk instead of in .env (Checkpoint 7,
    | App\Support\Secrets\SecretStore). Each entry names a secret and the
    | setting it fills in when the application starts. A stored secret wins
    | over the .env value; "php artisan navisuite:secrets import-env" moves
    | the values out of .env, and "navisuite:secrets status" shows where
    | each one comes from.
    |
    | path       Folder of the .secret files. Empty: storage/app/secrets.
    |            The Windows installer sets C:\NaviSuite\secrets\app.
    | protector  dpapi (Windows, this PC only), sodium (a key file in the
    |            same folder) or auto (dpapi where available).
    */
    'secrets' => [
        'path' => env('NAVISUITE_SECRETS_PATH'),
        'protector' => env('NAVISUITE_SECRETS_PROTECTOR', 'auto'),
        'map' => [
            'sync_node_token' => ['config' => 'sync.node_token', 'env' => 'SYNC_NODE_TOKEN'],
            'db_password' => ['config' => 'database.connections.vessel.password', 'env' => 'VESSEL_DB_PASSWORD'],
            'cloud_db_password' => ['config' => 'database.connections.mysql.password', 'env' => 'DB_PASSWORD'],
            'app_key' => ['config' => 'app.key', 'env' => 'APP_KEY'],
            'health_token' => ['config' => 'navisuite.health.token', 'env' => 'APP_HEALTH_TOKEN'],
            'mail_password' => ['config' => 'mail.mailers.smtp.password', 'env' => 'MAIL_PASSWORD'],
        ],
        // Settings that must come from the store on a vessel server: a
        // plaintext value in .env is reported by navisuite:status and /health.
        'required_on_vessel' => ['sync_node_token', 'db_password'],
    ],

];
