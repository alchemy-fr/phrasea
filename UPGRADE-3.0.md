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

Change HTML Chart version and phrasea image tag (i.e `3.2.2`)
You can also enable Sentry (don't forget DSN for PHP and Client).

If Databox was not enabled before, you need to enable it and uncheck "Run Migrations" from "Deployment Settings (Advanced)"
Then, after plan/apply, you can shell to the new `databox-api-php` and run the following commands:

```bash
bin/console doctrine:schema:drop -f || true
bin/install.sh
```

Then you should restore "Run Migrations" to checked and disable Databox back.
Then plan/apply

3. then run the following script line by line:

```bash
export CHART_VERSION=<chart-version>
bin/ops/configurator-configure.sh ${NAMESPACE} ${CHART_VERSION}
bin/ops/configurator-migrate.sh ${NAMESPACE} v20230807 ${CHART_VERSION}
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
