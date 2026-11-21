#!/usr/bin/env bash
# Full production-mode test.  Usage: bash tests/production.sh [--keep] [--quick]
#
# Builds a throw-away COPY of the app in storage/prodtest/ (gitignored), configured like the real host:
#   APP_ENV=production, APP_URL=https://localhost:<TLS_PORT>, fresh SQLite DB, strong owner password,
#   VIEW_CACHE_CHECK=false (trusted, pre-compiled cache), served by php -S behind a local TLS proxy (X-Forwarded-Proto: https).
# Then runs: preflight, lint, view-cache build, unit tests, security/production checks, smoke (en/fr/ar), and every functional + browser suite.
# Your working tree, .env and storage/w5d.sqlite are never touched.  --quick skips the browser suites; --keep keeps the copy in storage/prodtest (the servers are always stopped).
set -u
ROOT=$(cd "$(dirname "$0")/.." && pwd)
PT="$ROOT/storage/prodtest"
PHP_PORT=${PROD_PHP_PORT:-8081}; TLS_PORT=${PROD_TLS_PORT:-8443}
PASS='Prod-Test-Only-9x!'
KEEP=0; QUICK=0; for a in "$@"; do [ "$a" = --keep ] && KEEP=1; [ "$a" = --quick ] && QUICK=1; done
BASE="https://localhost:$TLS_PORT"
FAILED=(); STEPS=0
step() { STEPS=$((STEPS+1)); printf '\n=== %s\n' "$1"; }
run()  { local name=$1; shift; step "$name"; if "$@"; then echo "--- $name: PASS"; else echo "--- $name: FAIL"; FAILED+=("$name"); fi; }
# kill only OUR two servers (match by listen port, never by a pattern that could match this shell)
stop_servers() {
  for port in "$PHP_PORT" "$TLS_PORT"; do
    pids=$(ss -ltnpH "sport = :$port" 2>/dev/null | grep -o 'pid=[0-9]*' | cut -d= -f2 | sort -u)
    [ -n "$pids" ] && kill $pids 2>/dev/null
  done
  sleep 0.5
}
cleanup() {
  stop_servers
  [ "$KEEP" = 1 ] || rm -rf "$PT"
}
trap cleanup EXIT

step "build production copy ($PT)"
stop_servers
rm -rf "$PT"; mkdir -p "$PT/app"
( cd "$ROOT" && tar --exclude=./.git --exclude=./storage --exclude=./tools/node_modules --exclude=./tests/__pycache__ --exclude='*.pyc' -cf - . ) | ( cd "$PT/app" && tar -xf - )
mkdir -p "$PT/app/storage"/{logs,sessions,cache,framework/views,uploads,archive,backups}
HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_ARGON2ID);' "$PASS")
cat > "$PT/app/.env" <<EOF
APP_ENV=production
APP_URL=$BASE
APP_BASE_PATH=
APP_LANG=en
ADMIN_URL_PATH=panel-x9z
WEBMASTER_USERNAME=owner
WEBMASTER_PASSWORD_HASH='$HASH'
DB_DRIVER=sqlite
DB_PATH=storage/w5d.sqlite
STORAGE_DRIVER=null
ARCHIVE_DRIVER=prune
BACKUP_DRIVER=external
PAYMENT_MODE=demo
MAIL_DRIVER=log
MAIL_FOOTER_ADDRESS=W5D TECH, Tunis, Tunisia
CONTACT_EMAIL=owner@w5d.tech
PAYMENT_D17_ENABLED=true
PAYMENT_D17_GSM=+21620123456
PAYMENT_D17_NAME="W5D TECH"
VIEW_CACHE=true
VIEW_CACHE_CHECK=false
EOF
chmod 600 "$PT/app/.env"
echo "copy ready: $(du -sh "$PT/app" | cut -f1)"

step "TLS cert + servers"
openssl req -x509 -newkey rsa:2048 -nodes -days 2 -keyout "$PT/key.pem" -out "$PT/cert.pem" -subj "/CN=localhost" >/dev/null 2>&1 || { echo "openssl failed"; exit 2; }
( cd "$PT/app" && setsid -f php -S 127.0.0.1:$PHP_PORT -t htdocs htdocs/index.php > "$PT/php.log" 2>&1 < /dev/null )
sleep 1
( setsid -f python3 "$ROOT/tests/tls_proxy.py" $TLS_PORT $PHP_PORT "$PT/cert.pem" "$PT/key.pem" > "$PT/tls.log" 2>&1 < /dev/null )
sleep 1
for i in 1 2 3 4 5 6 7 8 9 10; do code=$(curl -ks -o /dev/null -w '%{http_code}' "$BASE/"); [ "$code" = 200 ] && break; sleep 1; done
echo "GET $BASE/ -> $code"; [ "$code" = 200 ] || { echo "server did not start"; cat "$PT/php.log" "$PT/tls.log"; exit 2; }

cd "$PT/app"
export PYTHONPATH="$PT/app/tests/_prod" W5D_OWNER_PASS="$PASS" SMOKE_CURL_OPTS="-k"

run "view cache build + status (deploy step)"       bash -c 'php bin/view.php cache && php bin/view.php status'
run "preflight (production copy must have 0 FAIL)"  env PREFLIGHT_ALLOW_LOCAL_URL=1 php bin/preflight.php
run "lint"                                          php bin/lint.php
run "view cache unit tests"                         php tests/viewcache.php
run "generated QCM pool (520,000 questions)"        php tests/qcmgen.php
run "production security checks"                    python3 tests/production.py "$BASE" "$PASS"
run "smoke: every route x en/fr/ar"                 bash tests/smoke.sh "$BASE"
run "flows (CSRF, forms, API, pricing, admin)"      python3 tests/flows.py "$BASE"
run "auth"                                          python3 tests/auth.py "$BASE"
run "d17 payments"                                  python3 tests/d17.py "$BASE"
run "payment gateways + owner config"              python3 tests/gateways.py "$BASE"
run "dashboards"                                    python3 tests/dash.py "$BASE"
run "sign-in gate"                                  python3 tests/gate.py "$BASE"
run "mail + unsubscribe"                            python3 tests/mail.py "$BASE"
run "CRUD + ACL"                                    python3 tests/crud.py "$BASE"
if [ "$QUICK" = 0 ]; then
  run "browser UI (Playwright)"                     python3 tests/ui.py "$BASE"
  run "auth modals / quick-find / tab bar (Playwright)" python3 tests/browser.py "$BASE"
  run "mobile audit (Playwright)"                   python3 tests/mobile.py "$BASE"
fi

step "post-run: no unhandled exceptions in the production log"
if grep -hE "Error:|Exception:" "$PT/app/storage/logs"/app-*.log 2>/dev/null | head -5 | grep . ; then echo "--- app log: FAIL"; FAILED+=("app log has exceptions"); else echo "--- app log: PASS (clean)"; fi

printf '\n==================== PRODUCTION TEST SUMMARY ====================\n'
printf 'steps run: %s   failed: %s\n' "$STEPS" "${#FAILED[@]}"
if [ ${#FAILED[@]} -gt 0 ]; then printf ' - %s\n' "${FAILED[@]}"; [ "$KEEP" = 1 ] && echo "copy kept in $PT (logs: php.log, app/storage/logs)"; exit 1; fi
echo "ALL GREEN"
