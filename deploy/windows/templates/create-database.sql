-- NaviSuite vessel server: the application database and its user.
--
-- Rendered by install-server.ps1 (Expand-NsTemplate) into the secrets folder,
-- run once by the MySQL root account through a temporary option file, and
-- deleted straight afterwards. This template holds placeholders only; the
-- password comes from the encrypted secret store (db_password) at install
-- time and is letters and digits only (New-NsSecret), so it needs no quoting.
--
-- Safe to run again (repair, reinstall over kept data): nothing is dropped,
-- an existing database and its data are kept, and the password is reset to
-- the stored secret so the application and MySQL always agree.
--
-- Rights: everything on the application's own database only, nothing global
-- and nothing on any other database. Triggers and routines for sync capture
-- are covered by ALL on that database; log_bin_trust_function_creators is set
-- server-wide in my.ini. MySQL listens on 127.0.0.1 only (my.ini), and the
-- user can only connect locally.

CREATE DATABASE IF NOT EXISTS `{{DB_NAME}}`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '{{DB_USER}}'@'127.0.0.1' IDENTIFIED WITH caching_sha2_password BY '{{DB_PASSWORD}}';
CREATE USER IF NOT EXISTS '{{DB_USER}}'@'localhost' IDENTIFIED WITH caching_sha2_password BY '{{DB_PASSWORD}}';
ALTER USER '{{DB_USER}}'@'127.0.0.1' IDENTIFIED WITH caching_sha2_password BY '{{DB_PASSWORD}}';
ALTER USER '{{DB_USER}}'@'localhost' IDENTIFIED WITH caching_sha2_password BY '{{DB_PASSWORD}}';

GRANT ALL PRIVILEGES ON `{{DB_NAME}}`.* TO '{{DB_USER}}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON `{{DB_NAME}}`.* TO '{{DB_USER}}'@'localhost';
