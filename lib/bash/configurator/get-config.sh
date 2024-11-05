#!/bin/sh

set -ex

FILE_KEY="config.json"
resource="/${CONFIGURATOR_STORAGE_BUCKET_NAME}/${FILE_KEY}"
CONTENT_TYPE="binary/octet-stream"
dateValue=`TZ=GMT date -R`
stringToSign="GET\n\n${CONTENT_TYPE}\n${dateValue}\n${resource}"
signature=`echo -en ${stringToSign} | openssl sha1 -hmac ${S3_SECRET_KEY} -binary | base64`

USE_PATH_STYLE=${CONFIGURATOR_STORAGE_USE_PATH_STYLE_ENDPOINT:-"false"}

ENDPOINT=${S3_ENDPOINT:-"s3.amazonaws.com"}
ENDPOINT_HOST=${ENDPOINT/"https://"/""}
if [ "$USE_PATH_STYLE" = "0" ] || [ "$USE_PATH_STYLE" = "false" ]; then
  ENDPOINT_HOST="${CONFIGURATOR_STORAGE_BUCKET_NAME}.${ENDPOINT_HOST}"
  BASE_URL="https://${ENDPOINT_HOST}"
else
  BASE_URL="https://${ENDPOINT_HOST}/${CONFIGURATOR_STORAGE_BUCKET_NAME}"
fi

CURL_OPTS=""
if [ "$VERIFY_SSL" = "0" ] || [ "$VERIFY_SSL" = "false" ]; then
  CURL_OPTS="${CURL_OPTS} --insecure"
fi

curl --fail-with-body \
  --no-progress-meter \
  ${CURL_OPTS} \
  -H "Host: ${ENDPOINT_HOST}" \
  -H "Date: ${dateValue}" \
  -H "Content-Type: ${CONTENT_TYPE}" \
  -H "Authorization: AWS ${S3_ACCESS_KEY}:${signature}" \
  "${BASE_URL}/${FILE_KEY}" -o /stack-config.json
