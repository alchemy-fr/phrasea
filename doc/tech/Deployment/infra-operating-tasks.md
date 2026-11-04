# Infra operating tasks

## Initialization and upgrades

The first time the stack is deployed we need to initialize some things:
- Create databases
- Add vhosts & permissions to rabbitmq
- Create the S3 buckets and the Keycloak realm, roles and clients
- etc.

Each image does it through its `bin/lifecycle.sh <phase>` script, which also
provisions what a new version adds and runs the migrations on an upgrade. The
phases, their order and their mapping to Docker Compose
([`/bin/setup.sh`](@phrasea-repo/bin/setup.sh),
[`/bin/migrate.sh`](@phrasea-repo/bin/migrate.sh)) and to the Helm chart hooks
are described in [Install and upgrade lifecycle](./lifecycle.md).

The configurator `configure` command, run on every upgrade, also replays the RabbitMQ configuration
(vhosts, permissions, `s3events` exchange, queue and binding): a missing vhost (renamed in the configuration, RabbitMQ
recreated or emptied) is recreated without manual intervention. It only runs while `CONFIGURATOR_CONFIGURE_RABBITMQ`
is true.
