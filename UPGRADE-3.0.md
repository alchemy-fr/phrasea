# UPGRADE FROM 2.x to 3.0

## Upgrade docker compose stack

```bash
bin/migrate.sh \
  && bin/setup.sh \
  && dc run --rm configurator migration:v20230807 -vvv
```

## Upgrade HELM release


1. Backup actual version:

```bash
export NAMESPACE=<namespace>
```

```bash
cd /path/to/phrasea
./bin/ops/export-all-k8s.sh "${NAMESPACE}"
```

2. Upgrade HELM release

Go to Cycloid and change HTML Chart version and phrasea image tag (i.e `3.2.2`)
You can also enable Sentry (don't forget DSN for PHP and Client).

If Databox was not enabled before, you need to enable it and uncheck "Run Migrations" from "Deployment Settings (Advanced)"
Then, after plan/apply, you can shell to the new `databox-api-php` and run the following commands:
```bash
bin/console doctrine:schema:drop -f || true
bin/install.sh
```
Then you should restore "Run Migrations" to checked and disable Databox back.
THen plan/apply

3. then run the following script line by line:

```bash
export MIGRATION_NAME=v20230807
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

Follow the logs (optional):
```bash
kubectl attach -it job/configurator-migrate-${MIGRATION_NAME}
```

Clean jobs:
```bash
kubectl delete job/configurator-migrate-${MIGRATION_NAME}
kubectl delete job/configurator-configure
```
