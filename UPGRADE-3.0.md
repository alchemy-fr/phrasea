# UPGRADE FROM 2.x to 3.0

## Upgrade docker compose stack

```bash
bin/setup.sh \
  && dc run --rm configurator bin/console migration:20230807
```

## Upgrade HELM release

Upgrade helm release then run the following job:

```bash
export MIGRATION_NAME=20230807
helm template <release-name> -f values.yaml \
  --set "configurator.executeMigration" "${MIGRATION_NAME}" \
    -s templates/job-tests.yaml | kubectl apply -f -
kubectl attach -it pod/${MIGRATION_NAME}
kubectl delete -it pod/${MIGRATION_NAME}
```



# TODO Link Accounts: Attach migrated users to newly configured IdP
