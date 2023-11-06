#!/bin/bash

set -e

NS=${NS:-"ps"}
RELEASE_NAME=${4:-"ps"}

kubectl config use-context minikube

source ./tmp/.helm.env

if [ -z "${OLD_CHART_DIR}" ]; then
  >&2 echo "Missing OLD_CHART_DIR"
  exit 1
fi

if [ -z "${NEW_CHART_DIR}" ]; then
  >&2 echo "Missing NEW_CHART_DIR"
  exit 1
fi

if [ -z "${NEW_CHART_VALUES}" ]; then
  >&2 echo "Missing NEW_CHART_VALUES"
  exit 1
fi

MIGRATION_NAME=20230807


# <TODO remove>
(cd ${NEW_CHART_DIR} \
  && helm -n ${NS} get values ${RELEASE_NAME} -o yaml > /tmp/.current-values.yaml \
  && helm -n ${NS} upgrade ${RELEASE_NAME} ./ \
    -f ${NEW_CHART_VALUES} \
    -f /tmp/.current-values.yaml \
    --set "configurator.executeMigration=${MIGRATION_NAME}" \
    --set "stack.runMigrations=false" \
)

exit
# </TODO remove>



read -p "Reset? (y/N)" RESET_RELEASE

if [[ $RESET_RELEASE == "y" ]]; then
  echo "Resetting release..."
  if [ -z "${OLD_CHART_VALUES}" ]; then
    >&2 echo "Missing OLD_CHART_VALUES env"
    exit 1
  fi

  helm uninstall ${RELEASE_NAME} --namespace $NS || true;
  kubectl delete ns $NS || true
  kubectl create ns $NS
  echo "Installing old chart ${RELEASE_NAME} in namespace $NS..."

  (cd "${OLD_CHART_DIR}" \
    && helm install ${RELEASE_NAME} ./ \
      -f "${OLD_CHART_VALUES}" \
      --namespace $NS \
  )

  echo ""
  echo ""

  read -p "Ready to migrate? Press enter!"
fi

echo "Migrating..."

(cd ${NEW_CHART_DIR} \
  && helm -n ${NS} get values ${RELEASE_NAME} -o yaml > /tmp/.current-values.yaml \
  && helm -n ${NS} upgrade ${RELEASE_NAME} ./ \
    -f ${NEW_CHART_VALUES} \
  && helm -n ${NS} upgrade ${RELEASE_NAME} ./ \
      -f ${NEW_CHART_VALUES} \
      -f /tmp/.current-values.yaml \
      --set "configurator.executeMigration=${MIGRATION_NAME}" \
  && helm -n ${NS} upgrade ${RELEASE_NAME} ./ \
      -f ${NEW_CHART_VALUES} \
      -f /tmp/.current-values.yaml \
      --set "configurator.executeMigration="
)
