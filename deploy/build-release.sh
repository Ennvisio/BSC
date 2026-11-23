#!/usr/bin/env bash
# Builds a NaviSuite release for the Windows vessel server.
#
#   deploy/build-release.sh [version]
#
# Output: build/navisuite-<version>.zip, holding one folder navisuite-<version>\
# with the application, production-only Composer packages, a RELEASE file and
# deploy\windows. Install it with deploy\windows\install-server.ps1, or update
# an existing server with update-server.ps1.
#
# The release is built from the working tree. It never contains secrets,
# database dumps, uploaded files, logs, tests or development tools.
#
# Checkpoint 8: it also carries release.json (product version from
# deploy/product.json, the sync protocol and client compatibility, and
# whether it is a test build), which the installer and update-server.ps1
# use for their version checks. NAVISUITE_TEST_BUILD=1 marks a test build
# (deploy/build-installers.sh sets it when the test gate was skipped).
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT=$(pwd)

if [[ $# -ge 1 ]]; then
    VERSION="$1"
else
    VERSION="$(date +%Y.%m.%d)-$(git rev-parse --short HEAD 2>/dev/null || echo nogit)"
    if [[ -n "$(git status --porcelain 2>/dev/null)" ]]; then VERSION="$VERSION-dirty"; fi
fi
if [[ ! "$VERSION" =~ ^[A-Za-z0-9._-]+$ ]]; then echo "Invalid version: $VERSION" >&2; exit 1; fi

NAME="navisuite-$VERSION"
OUT="$ROOT/build"
STAGE="$OUT/$NAME"
rm -rf "$STAGE" "$OUT/$NAME.zip"
mkdir -p "$STAGE"

echo "==> Copying the application ($VERSION)"
# Order matters: rsync uses the first rule that matches.
rsync -a --delete \
    --include='/deploy/windows/templates/*.sql' \
    --include='/.env.example' \
    --include='/.env.vessel.example' \
    --exclude='/.env*' \
    --exclude='.git/' --exclude='.gitignore' --exclude='.gitattributes' --exclude='.github/' \
    --exclude='.DS_Store' --exclude='.idea/' --exclude='.vscode/' --exclude='.claude/' \
    --exclude='.phpunit.result.cache' --exclude='.upgrade-backup*' \
    --exclude='/build/' --exclude='/node_modules/' --exclude='/vendor/' --exclude='/tests/' --exclude='/docs/' --exclude='/desktop/' \
    --exclude='/.well-known/' \
    --exclude='*.sql' --exclude='*.zip' --exclude='*.log' \
    --exclude='/phpunit.xml' --exclude='/CODEBASE_OVERVIEW.md' --exclude='/webpack.mix.js' \
    --exclude='/package.json' --exclude='/package-lock.json' \
    --exclude='/deploy/build-release.sh' --exclude='/deploy/build-installers.sh' --exclude='/deploy/installer/' \
    --include='/storage/app/unlocode/***' \
    --include='/storage/' --include='/storage/app/' --include='/storage/framework/' \
    --include='/storage/framework/*/' --include='/storage/framework/cache/data/' \
    --include='/storage/app/public/' --include='/storage/app/attachments/' --include='/storage/app/pms-imports/' \
    --include='/storage/logs/' \
    --exclude='/storage/**' \
    --include='/bootstrap/cache/' --exclude='/bootstrap/cache/*' \
    --include='/images/cert_copy/' --include='/images/signature/' --include='/images/userphoto/' \
    --exclude='/images/cert_copy/*' --exclude='/images/signature/*' --exclude='/images/userphoto/*' \
    --exclude='/public/images/**' \
    ./ "$STAGE/"

echo "==> Composer packages (production only)"
cp composer.json composer.lock "$STAGE/"
( cd "$STAGE" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts --quiet )
( cd "$STAGE" && composer dump-autoload --optimize --no-dev --no-scripts --quiet )

echo "$VERSION" > "$STAGE/RELEASE"

echo "==> release.json"
NS_RELEASE="$VERSION" NS_COMMIT="$(git rev-parse HEAD 2>/dev/null || echo unknown)" NS_TEST_BUILD="${NAVISUITE_TEST_BUILD:-0}" \
php -r '
    $product = json_decode(file_get_contents("deploy/product.json"), true, 512, JSON_THROW_ON_ERROR);
    $config = file_get_contents("config/navisuite.php");
    preg_match("/.client_api. => (\\d+)/", $config, $api);
    preg_match("/APP_MIN_CLIENT_VERSION., .([0-9.]+)./", $config, $min);
    preg_match("/const PROTOCOL = (\\d+);/", file_get_contents("app/Sync/Engine/SyncServer.php"), $protocol);
    if (! $api || ! $min || ! $protocol) { fwrite(STDERR, "Could not read the compatibility values.\n"); exit(1); }
    echo json_encode([
        "product" => $product["product"],
        "product_version" => $product["version"],
        "min_upgrade_from" => $product["min_upgrade_from"],
        "release" => getenv("NS_RELEASE"),
        "commit" => getenv("NS_COMMIT"),
        "built_at" => gmdate("Y-m-d\\TH:i:s\\Z"),
        "test_build" => getenv("NS_TEST_BUILD") === "1",
        "sync_protocol" => (int) $protocol[1],
        "client_api" => (int) $api[1],
        "min_client_version" => $min[1],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
' > "$STAGE/release.json"

echo "==> Checking the result"
fail=0
for bad in .env database.sqlite ennvisio_srd.sql vesselitemreq.sql Archive.zip; do
    if [[ -e "$STAGE/$bad" ]]; then echo "  FOUND $bad" >&2; fail=1; fi
done
leaks=$(find "$STAGE" \( -name '*.sql' -o -name '.env' -o -name '*.log' \) ! -path "$STAGE/deploy/windows/templates/*" -print)
if [[ -n "$leaks" ]]; then echo "$leaks" >&2; fail=1; fi
for f in images/cert_copy images/signature images/userphoto storage/app/attachments; do
    if [[ -n "$(find "$STAGE/$f" -type f ! -name '.gitignore' 2>/dev/null)" ]]; then echo "  Uploaded files in $f" >&2; fail=1; fi
done
for f in artisan index.php release.json .env.vessel.example vendor/autoload.php deploy/windows/install-server.ps1 deploy/windows/setup-server.ps1 deploy/windows/templates/create-database.sql storage/app/unlocode; do
    [[ -e "$STAGE/$f" ]] || { echo "  MISSING $f" >&2; fail=1; }
done
if [[ -d "$STAGE/vendor/phpunit" ]]; then echo "  Development packages present" >&2; fail=1; fi
if [[ $fail -ne 0 ]]; then echo "Release check failed." >&2; exit 1; fi

echo "==> Packing"
( cd "$OUT" && zip -qr -X "$NAME.zip" "$NAME" )
rm -rf "$STAGE"
SIZE=$(du -h "$OUT/$NAME.zip" | cut -f1)
echo "Built $OUT/$NAME.zip ($SIZE)"
