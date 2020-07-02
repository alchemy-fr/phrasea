#!/bin/bash

helm uninstall all1
kubectl delete job \
    postgesql-create-databases \
    auth-setup \
    expose-setup \
    notify-setup \
    uploader-setup \
    auth-migrate \
    expose-migrate \
    notify-migrate \
    uploader-migrate \
    postgresql-create-databases \
    rabbitmq-vhost-setup \
    auth-create-admin-oauth-client \
    uploader-create-admin-oauth-client \
    expose-create-admin-oauth-client \
    notify-create-admin-oauth-client

n=0
until [ "$n" -ge 50 ]; do
  helm install all1 ./all -f sample.yaml && break
  n=$((n+1))
  sleep 2
done
