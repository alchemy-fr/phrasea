#!/bin/bash

set -e

if [ -z "$1" ]; then
  echo "Missing URL"
  exit 1
fi

. bin/functions.sh

load-env

docker compose run \
  --rm -T \
  --entrypoint "sh -c" minio-mc "\
  set -x; \
  while ! (echo > /dev/tcp/minio/9000) 2>/dev/null; do echo 'Wait minio to startup...' && sleep 0.1; done; \
  mc alias set minio http://minio:9000 \$MINIO_ACCESS_KEY \$MINIO_SECRET_KEY \
  && curl --output /img.jpg "$1" \
  && mc cp /img.jpg minio/${INDEXER_BUCKET_NAME}/\$(date '+%Y-%m-%dT%H:%M:%S')/a/b/c/
"
