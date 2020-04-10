#!/bin/sh

while ! nc -z minio 9000; do echo 'Wait minio to startup...' && sleep 0.1; done;
sleep 5
mc config host add minio http://minio:9000 $MINIO_ACCESS_KEY $MINIO_SECRET_KEY
mc mb --ignore-existing minio/$EXPOSE_STORAGE_BUCKET_NAME
