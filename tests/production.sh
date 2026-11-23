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
cat > "$PT/app/.env" <<EOF
APP_ENV=production
APP_URL=$BASE
APP_BASE_PATH=
APP_LANG=en
ADMIN_URL_PATH=panel-x9z
OWNER_EMAIL=owner@w5d.tech
STAFF_EMAIL_DOMAIN=w5d.tech
STAFF_CODE_TTL_MIN=10
STAFF_IDLE_MINUTES=30
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
FUNDING_MODE=PRE_REGISTRY
F1_AUTO_ENTREPRENEUR_ENABLED=true
F1_TAX_RATE=0.005
F2_SPONSORSHIP_ENABLED=true
F2_SPONSORSHIP_MIN_TND=2700
F2_FUNDS_RESTRICTED=true
F2_REFUND_IF_ACTIVATION_FAILS=true
F3_PARTS_UNDER_PERSONAL_FILE=true
F4_PRE_SOLD_CREDITS_ENABLED=true
F4_CONDITION_PRECEDENT="matricule_fiscal_issued"
F4_ESCROW_REQUIRED=true
F5_STARTUP_ACT_APPLICATION=pending
F6_OWNER_SALARY_DEFERRED=true
F6_OWNER_SALARY_PUBLISHED=true
RNE_ACTIVATION_FEE_TND=25
RNE_BUDGET_TND=1500
VAT_MATRICULE_FISCAL=
ADS_STATUS=not_applied
ADS_MIN_CONTENT_PAGES=30
ADS_MIN_PAGE_WORDS=200
ADS_WITHDRAWAL_ALLOWED=false
ADS_ACCRUED_USD=0
ADS_ACCRUED_TND=0
ADS_WITHDRAWN_TND=0
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
run "preflight (production copy must have 0 FAIL)"  env PREFLIGHT_ALLOW_LOCAL_URL=1 PREFLIGHT_ALLOW_LOG_MAIL=1 php bin/preflight.php
run "lint"                                          php bin/lint.php
run "funding stack + AdSense fail-closed (tests 1-8)"  php tests/funding.php
run "trust pages: real content, 900 words x3, links, footer, sitemap" php tests/trust.php
run "view cache unit tests"                         php tests/viewcache.php
run "generated QCM pool (520,000 questions)"        php tests/qcmgen.php
run "production security checks"                    python3 tests/production.py "$BASE" "$PASS"
run "smoke: every route x en/fr/ar"                 bash tests/smoke.sh "$BASE"
run "flows (CSRF, forms, API, pricing, admin)"      python3 tests/flows.py "$BASE"
run "auth"                                          python3 tests/auth.py "$BASE"
run "d17 payments"                                  python3 tests/d17.py "$BASE"
run "payment gateways + owner config"              python3 tests/gateways.py "$BASE"
run "integrations (GA4/Ads/Meta/reCAPTCHA) + config"  python3 tests/integrations.py "$BASE"
run "sitemap.xml (complete, indexable, no gated/noindex)"  python3 tests/sitemap.py "$BASE"
run "motion + chromeless pages (browser)"           python3 tests/motion.py "$BASE"
run "dashboards"                                    python3 tests/dash.py "$BASE"
run "sign-in gate"                                  python3 tests/gate.py "$BASE"
run "mail + unsubscribe"                            python3 tests/mail.py "$BASE"
run "CRUD + ACL"                                    python3 tests/crud.py "$BASE"
run "team sign-in (@team e-mail + code) + privacy"  python3 tests/staff.py "$BASE"
run "Google OAuth branding / user-data policy guard" python3 tests/google.py "$BASE"
if [ "$QUICK" = 0 ]; then
  run "browser UI (Playwright)"                     python3 tests/ui.py "$BASE"
  run "auth modals / quick-find / tab bar (Playwright)" python3 tests/browser.py "$BASE"
  run "mobile audit (Playwright)"                   python3 tests/mobile.py "$BASE"
  # service workers cannot register behind the throw-away self-signed certificate: these two use the plain-HTTP port of the SAME production copy (127.0.0.1 is a secure context)
  run "help icons: tooltips + popups (Playwright)"   python3 tests/help.py "http://127.0.0.1:$PHP_PORT"
  run "installable portal + offline indicator (Playwright)" python3 tests/pwa.py "http://127.0.0.1:$PHP_PORT"
fi

step "post-run: no unhandled exceptions in the production log"
if grep -hE "Error:|Exception:" "$PT/app/storage/logs"/app-*.log 2>/dev/null | head -5 | grep . ; then echo "--- app log: FAIL"; FAILED+=("app log has exceptions"); else echo "--- app log: PASS (clean)"; fi

printf '\n==================== PRODUCTION TEST SUMMARY ====================\n'
printf 'steps run: %s   failed: %s\n' "$STEPS" "${#FAILED[@]}"
if [ ${#FAILED[@]} -gt 0 ]; then printf ' - %s\n' "${FAILED[@]}"; [ "$KEEP" = 1 ] && echo "copy kept in $PT (logs: php.log, app/storage/logs)"; exit 1; fi
echo "ALL GREEN"
