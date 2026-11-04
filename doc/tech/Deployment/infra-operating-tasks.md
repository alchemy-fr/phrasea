# Infra operating tasks

## Initialization (aka migration-0)

The first time the stack is deployed we need to initialize some things:
- Create databases
- Add vhosts & permissions to rabbitmq
- etc.

All the operations are described in [`/bin/setup.sh`](@phrasea-repo/bin/setup.sh).

## Migrations

Each deployment of a new version runs the migrations, described in [`/bin/migrate.sh`](@phrasea-repo/bin/migrate.sh)
(the `<app>-migrate` and `configurator-migrate` jobs of the Helm chart).

The configurator `synchronize` command, run by the configurator migration, also replays the RabbitMQ configuration
(vhosts, permissions, `s3events` exchange, queue and binding): a missing vhost (renamed in the configuration, RabbitMQ
recreated or emptied) is recreated without manual intervention. Set `CONFIGURATOR_CONFIGURE_RABBITMQ=false` to skip it.
