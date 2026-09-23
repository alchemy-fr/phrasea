#!/bin/bash
#
# Replay every Doctrine migration of each Symfony API on an empty PostgreSQL
# database, then check that the resulting schema matches the entity mapping.
#
# bin/setup.sh never replays migrations: it runs `doctrine:schema:update -f`
# and marks them all as executed. This is the only place where a migration is
# proven to run on a fresh install, and where mapping/migrations drift shows.
#
# Needs the `db` service up (bin/setup.sh or `docker compose up -d db`).
#
# Usage: bin/dev/test-migrations.sh [--keep]   (run from the repository root)
#   --keep   do not drop the <app>_migrations databases afterwards

set -e

. bin/functions.sh

load-env

KEEP="0"
[ "${1:-}" = "--keep" ] && KEEP="1"

APPS="
databox
expose
uploader
"

status=0

for app in ${APPS}; do
  service="${app}-api-php"
  db_name="${app}_migrations_test"

  echo "=== ${app}: replaying migrations on ${db_name}"

  # DB_NAME is what config/packages/doctrine.yaml resolves DATABASE_URL from.
  # app:database:configure only exists in databox (ltree extension).
  if docker compose run --rm -T -e DB_NAME="${db_name}" "${service}" su app -c "
      set -e
      bin/console doctrine:database:drop --force --if-exists
      bin/console doctrine:database:create --if-not-exists
      if bin/console list --raw | grep -q '^app:database:configure'; then
        bin/console app:database:configure
      fi
      bin/console doctrine:migrations:sync-metadata-storage
      bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
      bin/console doctrine:schema:validate
    "; then
    echo "=== ${app}: OK"
  else
    echo "=== ${app}: FAILED (migrations do not replay, or the schema drifted from the mapping)" >&2
    status=1
  fi

  if [ "${KEEP}" != "1" ]; then
    docker compose run --rm -T -e DB_NAME="${db_name}" "${service}" su app -c \
      "bin/console doctrine:database:drop --force --if-exists" >/dev/null 2>&1 || true
  fi
done

exit ${status}
