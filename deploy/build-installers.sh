#!/usr/bin/env bash
# Builds the two Windows installers:
#
#   NaviSuite-Server-Setup.exe   the vessel server (release + runtimes + client installer)
#   NaviSuite-Client-Setup.exe   the desktop client for the crew PCs
#
#   deploy/build-installers.sh --runtime <folder> [--test-build] [--makensis <path>]
#
# Output: build/installers/<release>/ with both installers, SHA256SUMS,
# build.json (what was built and the test results) and answers.example.ini.
#
# THE TEST GATE. A production build (no --test-build) is made only when:
#   - the working tree is clean, so the build is exactly a commit;
#   - the full PHP test suite passes with nothing failed and nothing skipped
#     (so the MySQL tests must run: set SYNC_MYSQL_TEST_DATABASE and the
#     other SYNC_MYSQL_TEST_* variables, see docs/checkpoint-2-sync-foundation.md);
#   - the desktop client's unit tests and its Electron smoke test pass;
#   - desktop/package.json has the version in deploy/product.json.
# --test-build skips the gate. The installers are then marked as test builds:
# the server installer says so on its first page, refuses a silent install
# without /TESTBUILD, and setup-server.ps1 refuses it unless told otherwise.
# A test build must never be installed on a vessel.
#
# --runtime: the folder made by deploy/windows/prepare-runtime.ps1 on a
# Windows PC (Apache, PHP, MySQL, WinSW, cacert.pem, vc_redist.x64.exe and
# runtime.json).
#
# makensis: NSIS 3. Found from --makensis, $MAKENSIS, the PATH, or the copy
# electron-builder downloads (desktop/node_modules must be installed).
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT=$(pwd)

RUNTIME=""
TEST_BUILD=0
MAKENSIS_BIN="${MAKENSIS:-}"
while [[ $# -gt 0 ]]; do
    case "$1" in
        --runtime) RUNTIME="$2"; shift 2 ;;
        --test-build) TEST_BUILD=1; shift ;;
        --makensis) MAKENSIS_BIN="$2"; shift 2 ;;
        -h|--help) sed -n '2,30p' "$0"; exit 0 ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
done
fail() { echo "FAILED: $*" >&2; exit 1; }
[[ -n "$RUNTIME" ]] || fail "--runtime <folder> is required (made by deploy/windows/prepare-runtime.ps1)."
RUNTIME=$(cd "$RUNTIME" && pwd)

PRODUCT_VERSION=$(php -r 'echo json_decode(file_get_contents("deploy/product.json"), true)["version"];')
CLIENT_VERSION=$(php -r 'echo json_decode(file_get_contents("desktop/package.json"), true)["version"];')
[[ "$PRODUCT_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail "deploy/product.json version must be X.Y.Z, not '$PRODUCT_VERSION'."
[[ "$CLIENT_VERSION" == "$PRODUCT_VERSION" ]] || fail "desktop/package.json is $CLIENT_VERSION but deploy/product.json is $PRODUCT_VERSION. Both installers carry one version."

COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo nogit)
DIRTY=""
[[ -z "$(git status --porcelain 2>/dev/null)" ]] || DIRTY="-dirty"
if [[ $TEST_BUILD -eq 1 ]]; then
    RELEASE="$PRODUCT_VERSION-test.$COMMIT$DIRTY"
    APP_VERSION="$PRODUCT_VERSION-test.$COMMIT"
else
    RELEASE="$PRODUCT_VERSION-$COMMIT"
    APP_VERSION="$PRODUCT_VERSION"
fi
OUT="$ROOT/build/installers/$RELEASE"
STAGE="$ROOT/build/installers/.stage-$RELEASE"
RESULTS="$OUT/test-results"
rm -rf "$OUT" "$STAGE"
mkdir -p "$OUT" "$STAGE/payload/client" "$RESULTS"

# ------------------------------------------------------------------ the gate
PHP_SUMMARY='"not run"'
DESKTOP_SUMMARY='"not run"'
SMOKE_SUMMARY='"not run"'
if [[ $TEST_BUILD -eq 0 ]]; then
    echo "==> Test gate"
    [[ -z "$DIRTY" ]] || fail "The working tree has uncommitted changes. A production build must be exactly a commit."
    [[ -n "${SYNC_MYSQL_TEST_DATABASE:-}" ]] || fail "SYNC_MYSQL_TEST_DATABASE is not set: the MySQL tests would be skipped, and the gate allows no skipped tests."

    echo "  PHP test suite"
    if ! php artisan test --log-junit "$RESULTS/php-junit.xml" > "$RESULTS/php.log" 2>&1; then
        tail -40 "$RESULTS/php.log" >&2
        fail "The PHP test suite did not pass (full output: $RESULTS/php.log)."
    fi
    PHP_SUMMARY=$(php -r '
        $x = simplexml_load_file($argv[1]);
        $s = $x->testsuite[0];
        $r = ["tests" => (int) $s["tests"], "assertions" => (int) $s["assertions"], "failures" => (int) $s["failures"], "errors" => (int) $s["errors"], "skipped" => (int) $s["skipped"]];
        echo json_encode($r);
        exit(($r["failures"] + $r["errors"] + $r["skipped"]) === 0 && $r["tests"] > 0 ? 0 : 1);
    ' "$RESULTS/php-junit.xml") || fail "The PHP test suite reports failures, errors or skipped tests: $PHP_SUMMARY"
    echo "    $PHP_SUMMARY"

    echo "  Desktop client unit tests"
    ( cd desktop && npm test ) > "$RESULTS/desktop.log" 2>&1 || { tail -30 "$RESULTS/desktop.log" >&2; fail "The desktop unit tests did not pass ($RESULTS/desktop.log)."; }
    DESKTOP_SUMMARY=$(grep -E '^. (tests|pass|fail) ' "$RESULTS/desktop.log" | awk '{print "\""$2"\": "$3}' | paste -sd, - | sed 's/^/{/; s/$/}/')
    echo "    $DESKTOP_SUMMARY"

    echo "  Desktop client smoke test (real Electron app)"
    ( cd desktop && env -u ELECTRON_RUN_AS_NODE npm run smoke ) > "$RESULTS/smoke.log" 2>&1 || { tail -30 "$RESULTS/smoke.log" >&2; fail "The Electron smoke test did not pass ($RESULTS/smoke.log)."; }
    SMOKE_SUMMARY='"passed"'
    echo "    passed"
else
    echo "==> TEST BUILD: the test gate is skipped. These installers must not be used on a vessel."
fi

# ------------------------------------------------------------------ runtime
echo "==> Runtime from $RUNTIME"
for f in runtime.json apache/bin/httpd.exe apache/bin/openssl.exe php/php.exe php/php8apache2_4.dll mysql/bin/mysqld.exe mysql/bin/mysql.exe mysql/bin/mysqldump.exe winsw/WinSW-x64.exe cacert.pem; do
    [[ -e "$RUNTIME/$f" ]] || fail "The runtime folder lacks $f. Make it with deploy/windows/prepare-runtime.ps1."
done
[[ -e "$RUNTIME/vc_redist.x64.exe" ]] || echo "  WARN: no vc_redist.x64.exe; PCs without the Visual C++ runtime will need it installed by hand."
php -r '$r = json_decode(file_get_contents($argv[1]), true); if (! isset($r["components"]["php"]["version"], $r["components"]["mysql"]["version"], $r["components"]["apache"]["version"])) { fwrite(STDERR, "runtime.json lacks versions\n"); exit(1); }' "$RUNTIME/runtime.json" \
    || fail "$RUNTIME/runtime.json is not from prepare-runtime.ps1."
rsync -a --delete "$RUNTIME/" "$STAGE/payload/runtime/"

# ------------------------------------------------------------------ release
echo "==> Release $RELEASE"
NAVISUITE_TEST_BUILD=$TEST_BUILD deploy/build-release.sh "$RELEASE"
( cd "$STAGE/payload" && unzip -q "$ROOT/build/navisuite-$RELEASE.zip" && mv "navisuite-$RELEASE" release )
[[ -f "$STAGE/payload/release/deploy/windows/setup-server.ps1" ]] || fail "The release has no setup-server.ps1."

# ------------------------------------------------------------------ client
echo "==> NaviSuite-Client-Setup.exe ($APP_VERSION)"
( cd desktop && env -u ELECTRON_RUN_AS_NODE npx electron-builder --win nsis --x64 --publish never -c.extraMetadata.version="$APP_VERSION" ) > "$OUT/client-build.log" 2>&1 \
    || { tail -30 "$OUT/client-build.log" >&2; fail "The client installer did not build ($OUT/client-build.log)."; }
CLIENT_EXE="desktop/dist/NaviSuite-Client-Setup-$APP_VERSION.exe"
[[ -f "$CLIENT_EXE" ]] || fail "$CLIENT_EXE was not produced."
cp "$CLIENT_EXE" "$OUT/NaviSuite-Client-Setup.exe"
cp "$CLIENT_EXE" "$STAGE/payload/client/NaviSuite-Client-Setup.exe"

# ------------------------------------------------------------------ build.json
php -r '
    $runtime = json_decode(file_get_contents($argv[1]), true);
    $release = json_decode(file_get_contents($argv[2]), true);
    echo json_encode([
        "product_version" => $release["product_version"],
        "release" => $release["release"],
        "commit" => $release["commit"],
        "test_build" => $release["test_build"],
        "built_at" => gmdate("Y-m-d\TH:i:s\Z"),
        "client_version" => $argv[3],
        "tests" => ["php" => json_decode($argv[4]), "desktop" => json_decode($argv[5]), "smoke" => json_decode($argv[6])],
        "runtime" => array_map(fn ($c) => $c["version"] ?? null, $runtime["components"]),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
' "$RUNTIME/runtime.json" "$STAGE/payload/release/release.json" "$APP_VERSION" "$PHP_SUMMARY" "$DESKTOP_SUMMARY" "$SMOKE_SUMMARY" > "$OUT/build.json"
cp "$OUT/build.json" "$STAGE/payload/build.json"

# ------------------------------------------------------------------ server
echo "==> NaviSuite-Server-Setup.exe"
if [[ -z "$MAKENSIS_BIN" ]]; then MAKENSIS_BIN=$(command -v makensis || true); fi
NSISDIR_ENV=""
if [[ -z "$MAKENSIS_BIN" ]]; then
    for d in "$HOME/Library/Caches/electron-builder" "$HOME/.cache/electron-builder"; do
        b=$(ls -d "$d"/nsis-3.*/nsis-3.*/ 2>/dev/null | grep -v resources | head -1 || true)
        if [[ -n "$b" ]]; then
            case "$(uname -s)" in Darwin) MAKENSIS_BIN="$b/mac/makensis" ;; *) MAKENSIS_BIN="$b/linux/makensis" ;; esac
            NSISDIR_ENV="${b%/}"
            break
        fi
    done
fi
[[ -n "$MAKENSIS_BIN" && -x "$MAKENSIS_BIN" ]] || fail "makensis (NSIS 3) not found. Install it, pass --makensis, or run 'npm run dist' in desktop/ once so electron-builder downloads it."
SIZE_MB=$(du -sm "$STAGE/payload" | cut -f1)
[[ $SIZE_MB -lt 1900 ]] || fail "The payload is ${SIZE_MB} MB; NSIS installers are limited to 2 GB."
env ${NSISDIR_ENV:+NSISDIR="$NSISDIR_ENV"} "$MAKENSIS_BIN" -V2 -WX \
    -DNS_VERSION="$PRODUCT_VERSION" -DNS_VERSION_QUAD="$PRODUCT_VERSION.0" -DNS_RELEASE="$RELEASE" \
    -DNS_PAYLOAD="$STAGE/payload" -DNS_OUTFILE="$OUT/NaviSuite-Server-Setup.exe" -DNS_TEST_BUILD="$TEST_BUILD" \
    deploy/installer/server/NaviSuite-Server.nsi > "$OUT/server-build.log" 2>&1 \
    || { tail -30 "$OUT/server-build.log" >&2; fail "makensis failed ($OUT/server-build.log)."; }

# ------------------------------------------------------------------ finish
cp deploy/installer/server/answers.example.ini "$OUT/"
( cd "$OUT" && shasum -a 256 NaviSuite-Server-Setup.exe NaviSuite-Client-Setup.exe > SHA256SUMS )
rm -rf "$STAGE" "$ROOT/build/navisuite-$RELEASE.zip"

echo
if [[ $TEST_BUILD -eq 1 ]]; then echo "TEST BUILD (not for vessels):"; else echo "Production build, test gate passed:"; fi
( cd "$OUT" && ls -lh NaviSuite-*.exe | awk '{print "  "$NF" ("$5")"}' && sed 's/^/  /' SHA256SUMS )
echo "  $OUT"
echo "Unsigned: Windows SmartScreen warns until the installers are code-signed (docs/checkpoint-8-installers.md)."
