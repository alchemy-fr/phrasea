# UPGRADE FROM 2.x to 3.0

## Upgrade docker compose stack

```bash
bin/migrate.sh \
  && bin/setup.sh \
  && dc run --rm configurator migration:v20230807 -vvv
```

## Upgrade HELM release

Upgrade helm release then run the following job:

```bash
export MIGRATION_NAME=v20230807
export RELEASE_NAME=<release-name>
helm -n ${RELEASE_NAME} get values -o yaml > /tmp/.current-values.yaml \
  && helm template ${RELEASE_NAME} -f /tmp/.current-values.yaml \
  --set "configurator.executeMigration=${MIGRATION_NAME}" \
  -s templates/job-tests.yaml | kubectl apply -f -
kubectl attach -it pod/${MIGRATION_NAME}
kubectl delete -it pod/${MIGRATION_NAME}
```
