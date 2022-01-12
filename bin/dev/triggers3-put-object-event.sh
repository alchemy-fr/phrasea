#!/bin/bash

set -e

. bin/functions.sh

load-env

docker-compose run --rm -T --entrypoint "sh -c" minio-mc "\
  set -x; \
  while ! nc -z minio 9000; do echo 'Wait minio to startup...' && sleep 0.1; done; \
    mc config host add minio http://minio:9000 \$MINIO_ACCESS_KEY \$MINIO_SECRET_KEY \
    && echo test > ~/new-object.txt \
    && mc cp ~/new-object.txt minio/\${DATABOX_STORAGE_BUCKET_NAME}
"
