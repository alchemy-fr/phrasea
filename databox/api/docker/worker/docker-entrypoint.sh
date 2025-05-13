#!/bin/sh

envsubst < ./docker/app/conf.d/symfony.ini > "$PHP_INI_DIR/conf.d/symfony.ini"

mkdir -p /etc/supervisor.d

for i in ${WORKER_PRIORITIES}; do
  export WORKER_CHANNEL="${i}"
  envsubst < ./docker/worker/worker.ini > /etc/supervisor.d/worker-${WORKER_CHANNEL}.ini
done

unset WORKER_CHANNEL

if [ "${NEWRELIC_ENABLED}" == "1" ]; then
    envsubst < ./docker/php/conf.d/newrelic.ini > "$PHP_INI_DIR/conf.d/newrelic.ini"
fi

POLICY_FILE="/etc/ImageMagick-${IMAGEMAGICK_POLICY_VERSION}/policy.xml"

if [ -f "${POLICY_FILE}" ]; then
  if [ ! -d $IMAGEMAGICK_POLICY_TEMPORARY_PATH ]; then
    echo "$IMAGEMAGICK_POLICY_TEMPORARY_PATH does not exist lets create it"
    mkdir -p $IMAGEMAGICK_POLICY_TEMPORARY_PATH
  fi

  sed -i -e "s|</policymap>||g" "${POLICY_FILE}"
  if [ -n "${IMAGEMAGICK_POLICY_MEMORY}" ]; then
    echo "  <policy domain=\"resource\" name=\"memory\" value=\"${IMAGEMAGICK_POLICY_MEMORY}\" />" >> "${POLICY_FILE}"
  fi
  if [ -n "${IMAGEMAGICK_POLICY_MAP}" ]; then
    echo "  <policy domain=\"resource\" name=\"map\" value=\"${IMAGEMAGICK_POLICY_MAP}\" />" >> "${POLICY_FILE}"
  fi
  if [ -n "${IMAGEMAGICK_POLICY_WIDTH}" ]; then
    echo "  <policy domain=\"resource\" name=\"width\" value=\"${IMAGEMAGICK_POLICY_WIDTH}\" />" >> "${POLICY_FILE}"
  fi
  if [ -n "${IMAGEMAGICK_POLICY_HEIGHT}" ]; then
    echo "  <policy domain=\"resource\" name=\"height\" value=\"${IMAGEMAGICK_POLICY_HEIGHT}\" />" >> "${POLICY_FILE}"
  fi
  if [ -n "${IMAGEMAGICK_POLICY_DISK}" ]; then
    echo "  <policy domain=\"resource\" name=\"disk\" value=\"${IMAGEMAGICK_POLICY_DISK}\" />" >> "${POLICY_FILE}"
  fi
  if [ -n "${IMAGEMAGICK_POLICY_AREA}" ]; then
    echo "  <policy domain=\"resource\" name=\"area\" value=\"${IMAGEMAGICK_POLICY_AREA}\" />" >> "${POLICY_FILE}"
  fi
  if [ -n "${IMAGEMAGICK_POLICY_TEMPORARY_PATH}" ]; then
    echo "  <policy domain=\"resource\" name=\"temporary-path\" value=\"${IMAGEMAGICK_POLICY_TEMPORARY_PATH}\" />" >> "${POLICY_FILE}"
  fi
  echo "</policymap>" >> "${POLICY_FILE}"
fi

su app -c 'php -d memory_limit=1G bin/console cache:clear'

exec docker-php-entrypoint "$@"
