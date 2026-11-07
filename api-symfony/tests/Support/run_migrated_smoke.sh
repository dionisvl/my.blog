#!/usr/bin/env bash
#
# Runs article_migrated_schema_smoke.php against a disposable, migrated PostgreSQL database.
#
# Usage (DATABASE_URL must already be set in the environment; it is never printed):
#   ARTICLE_SMOKE_DB_BASE=article_migration_20261103_0959 \
#   DATABASE_URL='postgresql://user:pass@host:5432/anything?serverVersion=16&charset=utf8' \
#     tests/Support/run_migrated_smoke.sh [publish|unpublish]
#
# ARTICLE_SMOKE_DB_BASE is the database name without the _test suffix. Doctrine's test
# configuration (dbname_suffix) appends _test, and the smoke script refuses any database
# whose name does not end in _test, so no production database can be targeted here.
# Only the pathname component of DATABASE_URL is replaced; credentials and query parameters are kept.

set -euo pipefail

fail() {
    echo "run_migrated_smoke.sh: $1" >&2
    exit "${2:-2}"
}

usage='usage: run_migrated_smoke.sh [publish|unpublish]'

if [ "$#" -gt 1 ]; then
    fail "$usage"
fi

mode="${1:-publish}"
case "$mode" in
    publish|unpublish) ;;
    *) fail "$usage" ;;
esac

base="${ARTICLE_SMOKE_DB_BASE-}"
if [ -z "$base" ]; then
    fail "ARTICLE_SMOKE_DB_BASE is required (database name without the _test suffix)"
fi

# Lowercase alphanumeric/underscore only, no _test suffix, and short enough that base + "_test" fits PostgreSQL's 63-byte identifier limit.
if ! [[ "$base" =~ ^[a-z0-9_]+$ ]] || [[ "$base" == *_test ]] || [ "${#base}" -gt 58 ]; then
    fail "ARTICLE_SMOKE_DB_BASE must be 1-58 lowercase alphanumeric/underscore characters and must not end in _test"
fi

if [ -z "${DATABASE_URL-}" ]; then
    fail "DATABASE_URL must be set"
fi

# Replace only the database path segment; the URL is passed via the environment so it never appears in argv or output.
target_url="$(ARTICLE_SMOKE_DB_NAME="$base" php -r '
$url = getenv("DATABASE_URL") ?: "";
$name = getenv("ARTICLE_SMOKE_DB_NAME") ?: "";
if (!preg_match("#^([a-zA-Z][a-zA-Z0-9+.-]*://[^/?\#]*)/([^/?\#]+)([?\#].*)?$#", $url, $m)) {
    fwrite(STDERR, "DATABASE_URL is malformed or has no database path\n");
    exit(1);
}
echo $m[1], "/", $name, $m[3] ?? "";
')" || fail "DATABASE_URL is malformed or has no database path" 1

if [ -z "$target_url" ]; then
    fail "could not build the target DATABASE_URL" 1
fi

export APP_ENV=test
export DATABASE_URL="$target_url"
unset ARTICLE_SMOKE_DB_BASE target_url base

cd -- "$(dirname -- "$0")/../.."

exec php tests/Support/article_migrated_schema_smoke.php "$mode"
