#!/usr/bin/env bash
# Smoke test: every public route x en/fr/ar must answer 200 (or expected code). Usage: tests/smoke.sh [base]
BASE=${1:-http://localhost:8080}; ROOT=$(cd "$(dirname "$0")/.." && pwd); fail=0; n=0
paths=(/ /join/ /how-it-works/ /countries/tunisia/ /partners/ /investors/ /pricing/ /about/ /ontology/ /careers/ /faq/ /status/ /trust/ /trust/statement/ /join/eligibility/ /checkout/ /subscription/ /sitemap/ /macro-works/ /services/ /projects/ /developers/ /agents/ /api/docs/developers.json /api/docs/agents.json /api/docs/agents/tools.json /llms.txt /llms-full.txt /sitemap.xml /robots.txt /api/index.json /api/price.json /api/kpis.json /api/macros.json /api/services.json /api/projects.json /api/governorates.json /for/ /for/owner/ /for/admin/ /for/leader/ /for/member/ /for/investor/ /for/customer/ /for/user/ /account/login/ /account/register/ /account/forgot/ /security.txt /.well-known/security.txt /humans.txt /manifest.webmanifest /api/openapi.json /api/agents.json /openapi.json /favicon.ico)
gated="/checkout/ /subscription/"
for f in $ROOT/data/content/macros/*.json; do paths+=("/macro-works/$(basename $f .json)/"); done
for f in $ROOT/data/content/services/*.json; do paths+=("/services/$(basename $f .json)/"); done
for f in $ROOT/data/content/projects/*.json; do paths+=("/projects/$(basename $f .json)/"); done
for g in $(php -r '$j=json_decode(file_get_contents("'$ROOT'/data/countries/tn.json"),true); foreach($j["regions"] as $g) echo ($g["slug"]??$g["id"]??"")," ";' 2>/dev/null); do paths+=("/tn/$g/"); done
for l in en fr ar; do for p in "${paths[@]}"; do
  out=$(curl $SMOKE_CURL_OPTS -s -o /tmp/smoke.out -w '%{http_code} %{redirect_url}' -b "w5d_lang=$l" "$BASE$p"); code=${out%% *}; loc=${out#* }; n=$((n+1))
  # sign-in gated forms (src/Gate.php): a guest gets 302 -> /account/login/?next=<same path> while flag gate_forms=1
  case " $gated " in *" $p "*) if [ "$code" = 302 ] && [[ "$loc" == */account/login/?next=* ]]; then continue; fi;; esac
  if [ "$code" != 200 ]; then echo "[$l] $p -> $code"; fail=$((fail+1)); fi
done; done
echo "checked $n, failed $fail"; [ $fail = 0 ]
