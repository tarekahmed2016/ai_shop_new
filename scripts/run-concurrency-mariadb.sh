#!/usr/bin/env bash
# Dedicated MariaDB/InnoDB concurrency suite.
# Refuses to run unless the target database name contains "test".
# Does not use Pest/ParaTest --parallel (forked workers already overlap).
set -euo pipefail

for arg in "$@"; do
  case "$arg" in
    --parallel|-p|--processes=*|--processes)
      echo "Refusing to run concurrency tests in Pest/ParaTest parallel mode." >&2
      exit 1
      ;;
  esac
done

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

HOST="${CONCURRENCY_DB_HOST:-127.0.0.1}"
PORT="${CONCURRENCY_DB_PORT:-3306}"
USER="${CONCURRENCY_DB_USERNAME:-root}"
PASSWORD="${CONCURRENCY_DB_PASSWORD:-}"
DATABASE="${CONCURRENCY_DB_DATABASE:-ai_shop_concurrency_test}"
MYSQL_BIN="${CONCURRENCY_MYSQL_BIN:-mysql}"

if [[ ! "$DATABASE" =~ [Tt][Ee][Ss][Tt] ]]; then
  echo "Refusing to run: CONCURRENCY_DB_DATABASE must contain 'test' (got: $DATABASE)" >&2
  exit 1
fi

if ! command -v "$MYSQL_BIN" >/dev/null 2>&1; then
  if [[ -x /opt/lampp/bin/mysql ]]; then
    MYSQL_BIN=/opt/lampp/bin/mysql
  else
    echo "mysql client not found. Set CONCURRENCY_MYSQL_BIN." >&2
    exit 1
  fi
fi

AUTH=(-h "$HOST" -P "$PORT" -u "$USER")
if [[ -n "$PASSWORD" ]]; then
  AUTH+=(-p"$PASSWORD")
fi

if ! "$MYSQL_BIN" "${AUTH[@]}" -e "SELECT 1" >/dev/null 2>&1; then
  echo "Cannot connect to MariaDB/MySQL at ${HOST}:${PORT}." >&2
  echo "Start a dedicated server, then:" >&2
  echo "  $MYSQL_BIN ${AUTH[*]} -e \"CREATE DATABASE IF NOT EXISTS \\\`${DATABASE}\\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\"" >&2
  exit 1
fi

"$MYSQL_BIN" "${AUTH[@]}" -e "CREATE DATABASE IF NOT EXISTS \`${DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

export CONCURRENCY_DB=mariadb
export CONCURRENCY_DB_HOST="$HOST"
export CONCURRENCY_DB_PORT="$PORT"
export CONCURRENCY_DB_USERNAME="$USER"
export CONCURRENCY_DB_PASSWORD="$PASSWORD"
export CONCURRENCY_DB_DATABASE="$DATABASE"

PHP_BIN="${PHP_BIN:-php}"

if [[ -n "${CONCURRENCY_TEST_FILTER:-}" ]]; then
  exec "$PHP_BIN" -d memory_limit=1G vendor/bin/pest \
    --configuration=phpunit.concurrency-mariadb.xml \
    --testsuite=Concurrency \
    --compact \
    --filter="$CONCURRENCY_TEST_FILTER"
fi

# Sequential files: each test already forks workers. Do not use Pest --parallel.
status=0
for file in tests/Feature/Concurrency/*.php; do
  echo "=== ${file} ==="
  if ! "$PHP_BIN" -d memory_limit=1G vendor/bin/pest \
    --configuration=phpunit.concurrency-mariadb.xml \
    --compact \
    "$file"; then
    status=1
  fi
done

exit "$status"
