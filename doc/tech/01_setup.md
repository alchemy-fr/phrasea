---
title: 'Setup Guide'
---

# Setup (with Docker Compose)

## Using a .env.local (Custom .env)

It may be easier to manage your environment variables using a local file.

You can add a `.env.local` file at the root of this project and define a command function in your `~/.bashrc` or `~/.zshrc`:

```bash
# ~/.bashrc or ~/.zshrc
function dc() {
    if [ -f .env.local ]; then
        docker compose --env-file=.env.local --env-file=.env --env-file=.env.local "$@"
    else
        docker compose "$@"
    fi
}
```

`.env.local` is passed twice on purpose. Docker Compose interpolates each env file as it reads it, so
derived values such as `DASHBOARD_CLIENT_URL=https://dashboard.${PHRASEA_DOMAIN}${HTTPS_PORT_PREFIX}`
are expanded while `.env` is parsed. Loading `.env.local` first makes `PHRASEA_DOMAIN` (or
`HTTPS_PORT_PREFIX`) available at that point, and loading it again last restores the plain
overrides (e.g. `TRAEFIK_HTTPS_PORT`) that `.env` would otherwise reset. Variables exported in the
shell still take precedence over both files. The `load-env` function in `bin/functions.sh` uses the
same order.

## Installation

> **Note:** If one of the ports is already allocated, see the [Changing ports](#changing-ports) section and run the command again.

1. Clone this repository
2. (Optional) Build all images for a fresh version:

```bash
bin/build.sh
```

### Secure your installation

Change all the default passwords or secrets you find in `.env`, by overriding them in `.env.local`.

They often start with `__CHANGE_ME_`. The default `ADMIN_BASIC_AUTH_USER` (basic auth of the
[administration consoles](#administration-consoles)) is public too, its password is written in
`.env`: generate your own with `htpasswd -nb admin 'my-secret-password'` and quote it in
`.env.local`, since the hash contains `$`:

```dotenv
ADMIN_BASIC_AUTH_USER='admin:$apr1$…'
```

Outside a development stack (`APP_ENV=dev`, or `docker-compose.dev.yml` in `COMPOSE_FILE`),
`bin/setup.sh` and `bin/migrate.sh` refuse to run, before any action, while a variable still starts
with `__CHANGE_ME_` or `ADMIN_BASIC_AUTH_USER` still has the value of `.env`, and list the
variables to change. For a demonstration only, `PHRASEA_ALLOW_DEFAULT_SECRETS=1` skips that check
(the CI sets it).

### Install dependencies and configure projects

* Run the following script to set up databases and initial configurations:

```bash
bin/setup.sh
```

If the stack is already deployed, you should run the migration after a fresh build:

```bash
bin/migrate.sh
```

### Configure Let's Encrypt

First, make sure you have set the `PHRASEA_DOMAIN` variable to your main domain. A wildcard certificate will be generated for that domain.

Add and configure the following lines in your `.env.local`:

```dotenv
TRAEFIK_PROVIDERS_FILE_FILENAME=
LETS_ENCRYPT_ENABLED=true
LETS_ENCRYPT_PROVIDER=gandiv5
LEGO_GANDIV5_API_KEY=<Your API key>
```

Then update the Traefik container:

```bash
dc up -d traefik
```

Wait for Traefik to obtain your certificate.

By default, Let's Encrypt's staging environment is used. To get a production certificate, set:

```dotenv
LETS_ENCRYPT_CA_SERVER=https://acme-v02.api.letsencrypt.org/directory
```

### Periodic tasks

The periodic tasks of databox, expose and uploader are planned by Symfony
Scheduler and consumed by dedicated containers, `databox-scheduler`,
`expose-scheduler` and `uploader-scheduler`.
They run the worker image with `WORKER_PRIORITIES` restricted to the
`scheduler_main` transport (a single process running the tasks of the `main`
schedule one after the other), while the `*-worker` services only consume the
RabbitMQ queues.

Keep **exactly one replica** of each scheduler container and never scale them:
scale the `*-worker` services instead.

The queues consumed by the databox workers, and how to split them between
deployments, are described in [Databox workers](./Databox/workers.md).

| Application | Task                                                                                         | Frequency    |
|-------------|----------------------------------------------------------------------------------------------|--------------|
| databox     | trigger the due automations of the saved searches                                            | every minute |
| databox     | `alchemy:notifier:digest:flush` (notification digests whose window has elapsed)              | hourly       |
| databox     | `app:integration:renew-tokens` (integration tokens about to expire)                          | hourly       |
| databox     | `app:file:unblock-analyses` (stuck file analyses, see `doc/tech/Databox/FileAnalyzers.mdx`) | hourly       |
| databox     | `app:trash:empty`                                                                            | daily        |
| databox     | `app:audit-log:purge` (retention `AUDIT_LOG_RETENTION_DAYS`, 365 days by default, see `doc/tech/Databox/audit-log.md`) | daily        |
| uploader    | `app:asset:clean-expired` (retention `ASSET_DAYS_RETENTION`, 31 days by default)             | daily        |
| all three   | `alchemy:storage:prune-multipart-uploads` (abandoned multipart uploads, see `doc/tech/Storage/01_s3.md`) | daily        |

Hourly and daily tasks start at a fixed minute derived from the task (daily
ones between 00:00 and 02:59), to spread the load. The commands can still be
run by hand; `bin/console app:automation:run-due` triggers the due automations
once, for development.

`SCHEDULER_OFFSET` (in seconds, `0` by default, negative to run earlier) shifts
every task of the schedulers (with `600`, each hourly and daily task runs 10
minutes later). Give each stack sharing the same resources its own offset so
that they do not run their tasks at the same time.

No host cron is needed: every periodic task runs in the scheduler containers.

### Changing ports

You can change service ports by overriding the environment variables (see the `.env` file).

### Networks and trusted proxies

Each container only joins the networks it needs, and only reaches the containers it shares one
with:

| Network  | Type       | Members                                                                                         |
|----------|------------|-------------------------------------------------------------------------------------------------|
| `proxy`  | bridge     | Traefik, the clients, the API nginx, Keycloak, Matomo nginx, the indexer, the webhook endpoint, the SAML IdPs |
| `app`    | `internal` | the API nginx and their php-fpm (FastCGI), Matomo nginx and php                                  |
| `data`   | `internal` | the databases, Redis, Elasticsearch, RabbitMQ, MinIO, Sockudo, the report API, the ML services, the consoles (pgAdmin, Mailpit…), and their clients: php, workers, Keycloak, the configurator, Traefik |
| `egress` | bridge     | php, workers, schedulers, Matomo php, the configurator, `dev`, k6: Internet and the host          |

`internal` networks have no Internet access and no route to the host: a data service never joins
`proxy`, a client never joins `data`. Traefik routes the services that are not on `proxy` through
`data` (label `traefik.docker.network=${COMPOSE_PROJECT_NAME}_data`). The services that publish a
port in development (`db`, `elasticsearch`…) also join `host-ports`, a bridge without NAT: Docker
publishes no port of a container that is only on internal networks.

A stack created when every container shared the single `internal` network must be recreated once:
`proxy` claims `PS_SUBNET`, still held by `<project>_internal` (`Pool overlaps with other one on this
address space`). Run `dc down` (the volumes are kept) then `dc up -d`.

`proxy` takes the `PS_SUBNET` subnet (`10.230.0.0/16` by default), whose gateway is
`PS_GATEWAY_IP`: the containers reach the public hosts of the stack through Traefik on the host
(`extra_hosts` → `PS_GATEWAY_IP`), from `proxy` or `egress`. Traefik gets the fixed address
`PS_TRAEFIK_IP` (`10.230.255.254`) in it, and `TRUSTED_PROXIES` defaults to that address: the APIs
and Keycloak only honour the `X-Forwarded-*` headers sent by Traefik, so that no other container can
spoof the client IP, host or scheme.

To run another stack on the same host, give it its own `PS_SUBNET` and change `PS_GATEWAY_IP` and
`PS_TRAEFIK_IP` along with it, e.g. in its `.env.local`:

```dotenv
PS_SUBNET=10.231.0.0/16
PS_GATEWAY_IP=10.231.0.1
PS_TRAEFIK_IP=10.231.255.254
```

Choose a `PS_SUBNET` outside the Docker address pools (`172.17.0.0/16` to `172.31.0.0/16`, then
`192.168.0.0/16` split in `/20`, when `/etc/docker/daemon.json` sets no `default-address-pools`). The
other networks (`app`, `data`, `egress`, those of other projects…) get their subnet from these pools,
which do not skip the `PS_SUBNET` of a stack stopped by `dc down`: a `PS_SUBNET` inside them can be
taken by another network while the stack is stopped, and the stack no longer starts (`Pool overlaps
with other one on this address space`).

To keep a `PS_SUBNET` in `172.16.0.0/12` anyway, move the Docker pools out of it in
`/etc/docker/daemon.json`, then restart Docker, choosing a base that overlaps no stack's `PS_SUBNET`:

```json
{
  "default-address-pools": [{"base": "10.210.0.0/16", "size": 24}]
}
```

Only set `TRUSTED_PROXIES` when another proxy (e.g. a load balancer) sits in front of Traefik: list
its address together with `PS_TRAEFIK_IP` (comma-separated) so that the client IP it forwards is kept.

### Elasticsearch authentication

The bundled Elasticsearch (profile `elasticsearch`) requires authentication, since the asset rights
are applied inside its documents. The `elastic` superuser gets `ELASTIC_PASSWORD`; the applications
use their own user, `ELASTICSEARCH_USERNAME` / `ELASTICSEARCH_PASSWORD` (both passwords are
`@change-me`). The `elasticsearch-setup` service creates or updates that user at every `dc up`, with a
role of the same name: every privilege on the indices starting with `ELASTICSEARCH_INDEX_PREFIX` (every
index when the prefix is empty, the default), `monitor` on the cluster. `bin/setup.sh`, `bin/migrate.sh`
and `bin/test.sh standard` run it in the foreground (`dc run --rm elasticsearch-setup`) before using
Elasticsearch. HTTP is not encrypted: Elasticsearch is only reachable from the internal `data` network.

With an external cluster, leave the `elasticsearch` profile out and set `ELASTICSEARCH_URL`,
`ELASTICSEARCH_USERNAME` and `ELASTICSEARCH_PASSWORD` (both empty for a cluster without
authentication).

### Database roles

By default every application connects to PostgreSQL with the superuser `POSTGRES_USER`: whoever
steals the credentials of one container (they are in its environment) gets every database, Keycloak
included. Give each application a role of its own in `.env.local`, both variables of each pair:

```dotenv
DATABOX_POSTGRES_USER=databox
DATABOX_POSTGRES_PASSWORD=…
EXPOSE_POSTGRES_USER=expose
EXPOSE_POSTGRES_PASSWORD=…
UPLOADER_POSTGRES_USER=uploader
UPLOADER_POSTGRES_PASSWORD=…
# Keycloak and keycloak2
KEYCLOAK_POSTGRES_USER=keycloak
KEYCLOAK_POSTGRES_PASSWORD=…
# report-api
REPORT_POSTGRES_USER=report
REPORT_POSTGRES_PASSWORD=…
```

An application whose pair is empty keeps the superuser. For each defined pair, the `database` step
of the configurator (`bin/setup.sh`, or `bin/console configure --filter database` in `configurator`)
connects as the superuser and, at every run:

1. creates the role (`LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE`), or resets its password;
2. creates the database with that owner, or gives it to the role;
3. gives the role the `public` schema and every table, sequence, view, materialized view and
   function of it (the extensions, such as Databox's `ltree`, stay with the superuser);
4. revokes `CONNECT` on the database from `PUBLIC` and grants it to the role only.

The roles of the three APIs also join the `configurator_editor` group, which reads and writes the
rows of the configurator database (global configuration, edited from the admin of each API), without
any DDL; `PUBLIC` loses `CONNECT` on that database as well. The superuser keeps access to everything:
the configurator, pgAdmin, `dev`, manual maintenance. Each API runs its Doctrine migrations with its
own role, owner of its database.

A role cannot be the superuser itself, nor another superuser: the configurator refuses both. With a
managed PostgreSQL or Kubernetes, where the roles are handled by the infrastructure, leave the
variables empty.

### MinIO users

By default every consumer of the bundled MinIO (profile `minio`) signs its requests with the root
user `S3_ACCESS_KEY`/`S3_SECRET_KEY`: whoever steals the environment of one container, a Next.js
client included, can read, overwrite and delete every bucket, and administer MinIO. Give each
consumer a user of its own in `.env.local`, both variables of each pair (the secret key takes at
least 8 characters):

```dotenv
# The API, its workers and scheduler
DATABOX_S3_ACCESS_KEY=databox
DATABOX_S3_SECRET_KEY=…
EXPOSE_S3_ACCESS_KEY=expose
EXPOSE_S3_SECRET_KEY=…
UPLOADER_S3_ACCESS_KEY=uploader
UPLOADER_S3_SECRET_KEY=…
# The Next.js clients (CONFIGURATOR_S3_ACCESS_KEY/_SECRET_KEY)
CONFIG_READER_S3_ACCESS_KEY=config-reader
CONFIG_READER_S3_SECRET_KEY=…
# databox-indexer
INDEXER_S3_ACCESS_KEY=indexer
INDEXER_S3_SECRET_KEY=…
```

A consumer whose pair is empty keeps the root user. For each defined pair, the `minio-setup` service
(profile `setup`, script `infra/docker/minio-setup/setup.sh`) logs in to MinIO as the root user with
`mc` and creates or replaces the policy `phrasea-<consumer>`, creates the user or resets its secret
key, and leaves it attached to that policy only:

| Pair             | Policy                                                                                                                                                      |
|------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `DATABOX_S3_*`, `EXPOSE_S3_*`, `UPLOADER_S3_*` | its bucket (`*_S3_BUCKET_NAME`): list it, read, write and delete objects, multipart uploads (presigned URLs are signed with these keys); `config.json` of the configurator bucket: read and write (the admin of each API pushes the global configuration) |
| `CONFIG_READER_S3_*` | the configurator bucket (`CONFIGURATOR_S3_BUCKET_NAME`, under `S3_PATH_PREFIX`): read                                                                    |
| `INDEXER_S3_*`   | `INDEXER_BUCKET_NAME`: list and read                                                                                                                        |

None of them can change the configuration of a bucket (policy, notifications, versioning), create
or delete one, or administer MinIO: the root user remains for the configurator (buckets, public
policy of the configurator bucket, AMQP notifications of the indexer bucket), `minio-setup`, the
MinIO console and `dev`. `bin/setup.sh` runs `minio-setup` after the configurator, `bin/migrate.sh`
and `bin/test.sh standard` at every run, all three only with the `minio` profile; by hand:

```bash
dc run --rm minio-setup
dc up -d        # the containers take their new keys
```

It is idempotent: run it again after changing a secret key. A pair emptied afterwards switches the
consumer back to the root user without removing its MinIO user (`mc admin user remove`). An access
key cannot be the root user, nor be shared by two consumers: `minio-setup` refuses both. The image
running `mc` is `MINIO_MC_IMAGE`, the MinIO image by default (it ships `mc`). With another S3
storage (AWS…), whose users or IAM roles are managed outside the stack, leave the pairs empty.

### Administration consoles

Traefik exposes the technical consoles behind two middlewares, defined on the `traefik` service:

- `admin-allowlist`: only the IP ranges of `ADMIN_ALLOWED_SOURCE_RANGES` (CIDR, comma-separated) get
  through, the others get a 403;
- `admin-console`: `admin-allowlist` followed by the basic auth `admin-secured`
  (`ADMIN_BASIC_AUTH_USER`, generated with `htpasswd -nb admin 'my-secret-password'`; the default
  one is public and refused outside a dev stack, see [Secure your installation](#secure-your-installation)).

| Console                                                                                             | Middleware        |
|-----------------------------------------------------------------------------------------------------|-------------------|
| Traefik, MinIO console, pgAdmin, phpMyAdmin, Mailpit, Grafana, Sockudo `/usage`, webhook endpoint | `admin-console`   |
| RabbitMQ console (authenticates with HTTP Basic itself)                                             | `admin-allowlist` |
| Keycloak `master` realm (`/admin/master/…`, `/realms/master/…`; the console sends its own Bearer token) | `admin-allowlist` |

`ADMIN_ALLOWED_SOURCE_RANGES` defaults to `0.0.0.0/0,::/0` (everybody): restrict it in production to
your VPN or office ranges, then run `dc up -d` to apply it:

```dotenv
ADMIN_ALLOWED_SOURCE_RANGES=203.0.113.0/24,10.8.0.0/16
```

The login of the users (`phrasea` realm), the S3 endpoint (`minio`), Matomo and the applications are
not restricted. Traefik checks the address of the client connected to it: when a load balancer sits
in front of Traefik, set the `ipStrategy.depth` of `admin-allowlist` and the `forwardedHeaders.trustedIPs`
of the entrypoint, otherwise the load balancer's address is checked.

The configurator reaches the RabbitMQ management API at `RABBITMQ_MANAGEMENT_URL`
(`http://rabbitmq:15672`, inside the Docker network) rather than through Traefik, and falls back to
`RABBITMQ_CONSOLE_URL` when it is empty (e.g. a managed RabbitMQ only reachable by its public URL).

### Using fixtures

You may want to populate databases with a set of fixtures:

```bash
# Warning! This will empty the databases, insert fixtures, and run bin/setup.sh again.
bin/dev/reset-db-fixtures.sh
```

The databox fixtures (three business workspaces, their users and rules) are described in
[Databox fixtures](./Databox/fixtures.md).

* Read the documentation for each group of services to customize environment variables:
    * [databox](./Databox/01_intro.md)
    * [expose](./Expose/01_intro.md)
    * [uploader](./Uploader/01_intro.md)

* Start the whole stack:

```bash
dc up -d
```

## Next steps

If you are a developer, follow the [dev setup guide](02_dev.md)
