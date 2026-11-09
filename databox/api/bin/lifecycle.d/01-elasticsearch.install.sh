# Sourced by the common lifecycle script (alchemy/core-bundle).

is_es_ready() {
    if [ -n "${ELASTICSEARCH_USERNAME}" ]; then
        set -- -u "${ELASTICSEARCH_USERNAME}:${ELASTICSEARCH_PASSWORD}"
    fi
    [ "$(curl "$@" --write-out '%{http_code}' --silent --output /dev/null "${ELASTICSEARCH_URL}/_cat/health?h=st")" = 200 ]
}

wait_for "Elasticsearch" is_es_ready

"${CONSOLE}" fos:elastica:reset
php -d memory_limit=1G "${CONSOLE}" fos:elastica:populate --no-debug
