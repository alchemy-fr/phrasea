# UPGRADE FROM 2.x to 3.0

## Upgrade docker compose stack

```bash
bin/migrate.sh \
  && bin/setup.sh \
  && dc run --rm configurator migration:v20230807 -vvv
```

## Upgrade HELM release

Upgrade helm release then run the following script line by line:

```bash
export MIGRATION_NAME=v20230807
export NAMESPACE=<namespace>
export RELEASE_NAME=phrasea
```

```bash
helm -n ${NAMESPACE} get values ${RELEASE_NAME} -o yaml > /tmp/.current-values.yaml
```

```bash
cd /path/to/alchemy-helm-charts-repo
cd charts/phrasea
git pull
```

```bash
helm template ${RELEASE_NAME} ./ -f /tmp/.current-values.yaml \
-s templates/configurator/configure-job.yaml | kubectl apply -f -
```

```bash
helm template ${RELEASE_NAME} ./ -f /tmp/.current-values.yaml \
--set "configurator.executeMigration=${MIGRATION_NAME}" \
-s templates/configurator/migration-job.yaml | kubectl apply -f -
```

```bash
kubectl attach -it job/configurator-migrate-${MIGRATION_NAME}
```

```bash
kubectl delete job/configurator-migrate-${MIGRATION_NAME}
kubectl delete job/configurator-configure
```
