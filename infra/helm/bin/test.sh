#!/bin/bash

kubectl config use-context minikube

helm install --dry-run --debug all1 ./all -f sample.yaml || exit 1

helm uninstall all1

sleep 120

n=0
until [ "$n" -ge 50 ]; do
  helm install all1 ./all -f sample.yaml && break
  n=$((n+1))
  sleep 2
done
