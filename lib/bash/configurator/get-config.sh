#!/bin/sh

set -ex

OUTPUT_FILE=/etc/app/stack-config.json

CONFIG_IS_PUBLIC=${CONFIG_IS_PUBLIC:-"false"}

FILE_KEY="config.json"

USE_PATH_STYLE=${CONFIGURATOR_S3_USE_PATH_STYLE_ENDPOINT:-"false"}

ENDPOINT=${CONFIGURATOR_S3_ENDPOINT:-"s3.amazonaws.com"}
ENDPOINT_HOST=${ENDPOINT/"https://"/""}
if [ "$USE_PATH_STYLE" = "0" ] || [ "$USE_PATH_STYLE" = "false" ]; then
  ENDPOINT_HOST="${CONFIGURATOR_S3_BUCKET_NAME}.${ENDPOINT_HOST}"
  BASE_URL="https://${ENDPOINT_HOST}"
else
  BASE_URL="https://${ENDPOINT_HOST}/${CONFIGURATOR_S3_BUCKET_NAME}"
fi

BASE_URL="${BASE_URL}/${CONFIGURATOR_S3_PATH_PREFIX}"

function fetch_config() {
  if [ "$CONFIG_IS_PUBLIC" = "0" ] || [ "$CONFIG_IS_PUBLIC" = "false" ]; then
    WGET_OPTS=""
    if [ "$VERIFY_SSL" = "0" ] || [ "$VERIFY_SSL" = "false" ]; then
      WGET_OPTS="${WGET_OPTS} --no-check-certificate"
    fi
    wget \
      ${WGET_OPTS} \
      -q \
      -O ${OUTPUT_FILE} \
      --no-verbose "${BASE_URL}${FILE_KEY}" || (echo "{}" > ${OUTPUT_FILE})
  else
    CURL_OPTS=""
    if [ "$VERIFY_SSL" = "0" ] || [ "$VERIFY_SSL" = "false" ]; then
      CURL_OPTS="${CURL_OPTS} --insecure"
    fi
    RESOURCE="/${CONFIGURATOR_S3_BUCKET_NAME}/${CONFIGURATOR_S3_PATH_PREFIX}${FILE_KEY}"
    CONTENT_TYPE="binary/octet-stream"
    DATE_VALUE=`TZ=GMT date -R`
    STR_TO_SIGN="GET\n\n${CONTENT_TYPE}\n${DATE_VALUE}\n${RESOURCE}"
    SIGNATURE=`echo -en ${STR_TO_SIGN} | openssl sha1 -hmac ${CONFIGURATOR_S3_SECRET_KEY} -binary | base64`
    curl --fail-with-body \
      --no-progress-meter \
      ${CURL_OPTS} \
      -H "Host: ${ENDPOINT_HOST}" \
      -H "Date: ${DATE_VALUE}" \
      -H "Content-Type: ${CONTENT_TYPE}" \
      -H "Authorization: AWS ${CONFIGURATOR_S3_ACCESS_KEY}:${SIGNATURE}" \
      "${BASE_URL}/${FILE_KEY}" -o ${OUTPUT_FILE} || (echo "{}" > ${OUTPUT_FILE})
  fi
}

n=0
until [ "$n" -ge 15 ]
do
   fetch_config && break
   n=$((n+1))
   sleep 1
done
