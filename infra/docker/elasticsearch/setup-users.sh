#!/bin/bash
#
# Creates or updates, as the `elastic` superuser, the user the applications
# reach the bundled Elasticsearch with (ELASTICSEARCH_USERNAME), and its role:
# every privilege on the indices starting with ELASTICSEARCH_INDEX_PREFIX (all
# the indices when it is empty), `monitor` on the cluster.
#
# Replayed at every start of the `elasticsearch-setup` service: both requests
# are upserts, so that a new ELASTICSEARCH_PASSWORD or index prefix is applied.

set -euo pipefail

ES_URL="${ES_URL:-http://elasticsearch:9200}"
WAIT_TIMEOUT="${ES_WAIT_TIMEOUT:-300}"

if [ -z "${ELASTIC_PASSWORD:-}" ]; then
    echo "ELASTIC_PASSWORD is empty: the bundled Elasticsearch has no superuser password." >&2
    exit 1
fi
if [ -z "${ELASTICSEARCH_USERNAME:-}" ]; then
    echo "ELASTICSEARCH_USERNAME is empty: the bundled Elasticsearch requires authentication." >&2
    exit 1
fi
if [ "${ELASTICSEARCH_USERNAME}" = "elastic" ]; then
    echo "The applications use the elastic superuser: no user to create (prefer a dedicated user)."
    exit 0
fi
if [ -z "${ELASTICSEARCH_PASSWORD:-}" ]; then
    echo "ELASTICSEARCH_PASSWORD is empty." >&2
    exit 1
fi

ROLE="${ELASTICSEARCH_USERNAME}"
INDEX_PATTERN="${ELASTICSEARCH_INDEX_PREFIX:-}*"
RESPONSE_FILE=$(mktemp)
trap 'rm -f "${RESPONSE_FILE}"' EXIT

json_string() {
    printf '"%s"' "$(printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')"
}

# Usage: es_request <method> <path> [body]; prints the HTTP status code.
es_request() {
    local args=(--silent --show-error --output "${RESPONSE_FILE}" --write-out '%{http_code}'
        --user "elastic:${ELASTIC_PASSWORD}" --request "$1" "${ES_URL}$2")
    if [ $# -ge 3 ]; then
        args+=(--header 'Content-Type: application/json' --data-binary "$3")
    fi
    # On a connection error, curl writes 000 as the status code.
    curl "${args[@]}" 2>/dev/null || true
}

echo "Waiting for Elasticsearch at ${ES_URL}..."
deadline=$((SECONDS + WAIT_TIMEOUT))
while true; do
    status=$(es_request GET '/_cluster/health?wait_for_status=yellow&timeout=5s')
    if [ "${status}" = 200 ]; then
        break
    fi
    if [ ${SECONDS} -ge ${deadline} ]; then
        echo "Elasticsearch not ready after ${WAIT_TIMEOUT}s (last HTTP status: ${status})." >&2
        if [ "${status}" = 401 ]; then
            echo "The elastic password is not ELASTIC_PASSWORD (changed through the API?)." >&2
        fi
        exit 1
    fi
    sleep 3
done

put() {
    local status
    status=$(es_request PUT "$1" "$2")
    if [ "${status}" != 200 ]; then
        echo "PUT $1 failed (HTTP ${status}):" >&2
        cat "${RESPONSE_FILE}" >&2
        echo >&2
        exit 1
    fi
}

put "/_security/role/${ROLE}" "{
    \"cluster\": [\"monitor\"],
    \"indices\": [{\"names\": [$(json_string "${INDEX_PATTERN}")], \"privileges\": [\"all\"]}]
}"
put "/_security/user/${ELASTICSEARCH_USERNAME}" "{
    \"password\": $(json_string "${ELASTICSEARCH_PASSWORD}"),
    \"roles\": [$(json_string "${ROLE}")]
}"

echo "Elasticsearch user \"${ELASTICSEARCH_USERNAME}\" ready (indices: ${INDEX_PATTERN})."
