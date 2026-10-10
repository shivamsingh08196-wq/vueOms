#!/usr/bin/env bash
# Cheap repo-wide checks that catch common AI-generated mistakes. Exit 1 on any finding.
set -u
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$ROOT"
bad=0
FRONTEND="resources/js"
[[ -d "$FRONTEND" ]] || { echo "✘ required frontend directory missing: $FRONTEND" >&2; exit 2; }
check() { local desc="$1" pattern="$2"; shift 2; local out; out=$(grep -rnE "$pattern" "$@" 2>/dev/null | head -20 || true); if [[ -n "$out" ]]; then echo "✘ $desc"; echo "$out"; bad=1; else echo "✔ $desc"; fi; }
[[ -d app && -d routes && -d database ]] || { echo "✘ conventional Laravel app/, routes/, database/ directories are required" >&2; exit 2; }
check "no debug helpers in PHP" '\b(dd|dump|var_dump|ray)\(' app routes database --include=*.php
check "no raw SQL with interpolated vars" '(DB::(select|statement|raw|unprepared)|whereRaw|orderByRaw|selectRaw)\([^;]*\$' app --include=*.php
check "no request->all() into models" '(create|update|fill|forceFill)\(\$request->all\(\)\)' app --include=*.php
check "no float casts for money" "'(price|total|subtotal|tax_amount|discount_amount|unit_price|line_total)' *=> *'(float|double)'" app --include=*.php
check "models never set guarded = []" 'guarded *= *\[\]' app --include=*.php
check "no console.log/debug in Vue JavaScript frontend" 'console\.(log|debug)\(' "$FRONTEND" --include=*.js --include=*.vue
check "no v-html unless reviewed" 'v-html' "$FRONTEND" --include=*.vue
check "no localStorage access outside store" 'localStorage\.(get|set)Item' "$FRONTEND" --include=*.js --include=*.vue --exclude-dir=stores --exclude-dir=tests
check "no focused/skipped tests" '\.(only|skip|fixme)\(|->(only|skip)\(|\bxit\(|\bxdescribe\(' qa-kit/e2e/tests qa-kit/e2e/support qa-kit/tests "$FRONTEND" tests --include=*.js --include=*.vue --include=*.ts --include=*.php
check "no hard waits in e2e" 'waitForTimeout\(|page\.pause\(' qa-kit/e2e/tests qa-kit/e2e/support
check "no committed secrets/passwords in code" '(DB_PASSWORD|APP_KEY|SECRET|API_KEY)=[^ ]{6,}' app config "$FRONTEND" README.md
if [[ ! -f package-lock.json || ! -f composer.lock ]]; then echo "✘ package-lock.json and composer.lock must be committed"; bad=1; else echo "✔ lockfiles present"; fi
nested_composer=$(find . -name composer.json -not -path './vendor/*' -not -path './.git/*' | grep -v '^./composer.json$' || true)
if [[ -n "$nested_composer" ]]; then echo "✘ nested composer.json found (use the single root composer.json):"; echo "$nested_composer"; bad=1; else echo "✔ single root composer.json"; fi
nested=$(find . -name package.json -not -path './node_modules/*' -not -path './vendor/*' -not -path './public/*' -not -path './.git/*' | grep -v '^./package.json$' || true)
if [[ -n "$nested" ]]; then echo "✘ nested package.json found (use the single root package.json):"; echo "$nested"; bad=1; else echo "✔ single root package.json"; fi
if git rev-parse --git-dir >/dev/null 2>&1; then tracked=$(git ls-files | grep -E '(^|/)\.env(\.e2e)?$' || true); if [[ -n "$tracked" ]]; then echo "✘ env files tracked in git:"; echo "$tracked"; bad=1; else echo "✔ no .env tracked"; fi; fi
exit "$bad"
