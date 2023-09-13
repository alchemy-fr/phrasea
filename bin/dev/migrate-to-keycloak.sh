#!/bin/bash

set -ex

function usage() {
  echo "Usage:"
  echo "$0 <old-chart> <new-chart> <new-chart-values>"
}

if [ -z "${1}" ]; then
  >&2 echo "Missing old-chart argument"
  usage
  exit 1
fi
if [ -z "${2}" ]; then
  >&2 echo "Missing new-chart argument"
  usage
  exit 1
fi
if [ -z "${3}" ]; then
  >&2 echo "Missing new-chart-values argument"
  usage
  exit 1
fi

NS=${NS:-"ps"}
RELEASE_NAME=${4:-"ps"}
OLD_CHART="$1"
NEW_CHART="$2"
NEW_CHART_VALUES="$3"

kubectl config use-context minikube

if [ ! -z "${PS_RESET_RELEASE}" ]; then
  if [ -z "${PS_OLD_CHART_VALUES}" ]; then
    >&2 echo "Missing PS_OLD_CHART_VALUES env"
    exit 1
  fi

  kubectl create ns $NS || true
  kubectl -n $NS delete jobs --all
  helm uninstall ${RELEASE_NAME} --namespace $NS || true;
  kubectl -n $NS delete pvc elasticsearch-databox-master-elasticsearch-databox-master-0 || true
  while [ $(kubectl -n $NS get pvc | wc -l) -gt 0 ] || [ $(kubectl -n $NS get pods | wc -l) -gt 0 ]
  do
    echo "Waiting for resources to be deleted..."
    sleep 2
  done

  echo "Installing old chart ${RELEASE_NAME} in namespace $NS..."
  helm install ${RELEASE_NAME} "${OLD_CHART}" \
      -f "${PS_OLD_CHART_VALUES}" \
      --namespace $NS

  echo ""
  echo ""
fi

echo "Migrating..."

NEW_CHART_DIR="${NEW_CHART}/.."

if [ ! -L "${NEW_CHART_DIR}/${RELEASE_NAME}" ]; then
  ln -s "${NEW_CHART_DIR}/phrasea" "${NEW_CHART_DIR}/${RELEASE_NAME}"
fi

(cd ${NEW_CHART}/.. \
  && helm -n ${NS} get values ${RELEASE_NAME} -o yaml > /tmp/.current-values.yaml \
  && helm -n ${NS} upgrade ${RELEASE_NAME} ./${RELEASE_NAME} \
    -f /tmp/.current-values.yaml \
    -f ${NEW_CHART_VALUES} \
  && helm -n ${NS} template ${RELEASE_NAME} -f /tmp/.current-values.yaml \
    --set "configurator.executeMigration=${MIGRATION_NAME}"
)
