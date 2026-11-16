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

Change all the default passwords or secrets you find in `.env`.

They often start with `__CHANGE_ME_`.

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

The periodic tasks of databox and uploader are planned by Symfony Scheduler and
consumed by dedicated containers, `databox-scheduler` and `uploader-scheduler`.
They run the worker image with `WORKER_PRIORITIES` restricted to the
`scheduler_main` transport (a single process running the tasks of the `main`
schedule one after the other), while the `*-worker` services only consume the
RabbitMQ queues.

Keep **exactly one replica** of each scheduler container and never scale them:
scale the `*-worker` services instead.

| Application | Task                                                                                         | Frequency    |
|-------------|----------------------------------------------------------------------------------------------|--------------|
| databox     | trigger the due automations of the saved searches                                            | every minute |
| databox     | `alchemy:notifier:digest:flush` (notification digests whose window has elapsed)              | hourly       |
| databox     | `app:integration:renew-tokens` (integration tokens about to expire)                          | hourly       |
| databox     | `app:file:unblock-analyses` (stuck file analyses, see `doc/tech/Databox/FileAnalyzers.mdx`) | hourly       |
| databox     | `app:trash:empty`                                                                            | daily        |
| databox     | `app:audit-log:purge` (retention `AUDIT_LOG_RETENTION_DAYS`, 365 days by default, see `doc/tech/Databox/audit-log.md`) | daily        |
| uploader    | `app:asset:clean-expired` (retention `ASSET_DAYS_RETENTION`, 31 days by default)             | daily        |

Hourly and daily tasks start at a fixed minute derived from the task (daily
ones between 00:00 and 02:59), to spread the load. The commands can still be
run by hand; `bin/console app:automation:run-due` triggers the due automations
once, for development.

`SCHEDULER_OFFSET` (in seconds, `0` by default, negative to run earlier) shifts
every task of both schedulers (with `600`, each hourly and daily task runs 10
minutes later). Give each stack sharing the same resources its own offset so
that they do not run their tasks at the same time.

No host cron is needed: every periodic task runs in the scheduler containers.

### Changing ports

You can change service ports by overriding the environment variables (see the `.env` file).

### Network and trusted proxies

The containers share the `PS_SUBNET` network (`172.30.0.0/16` by default), whose gateway is
`PS_GATEWAY_IP`. Traefik gets the fixed address `PS_TRAEFIK_IP` (`172.30.255.254`) in it, and
`TRUSTED_PROXIES` defaults to that address: the APIs and Keycloak only honour the `X-Forwarded-*`
headers sent by Traefik, so that no other container can spoof the client IP, host or scheme.

To run another stack on the same host, give it its own `PS_SUBNET` and change `PS_GATEWAY_IP` and
`PS_TRAEFIK_IP` along with it, e.g. in its `.env.local`:

```dotenv
PS_SUBNET=172.31.0.0/16
PS_GATEWAY_IP=172.31.0.1
PS_TRAEFIK_IP=172.31.255.254
```

Only set `TRUSTED_PROXIES` when another proxy (e.g. a load balancer) sits in front of Traefik: list
its address together with `PS_TRAEFIK_IP` (comma-separated) so that the client IP it forwards is kept.

### Administration consoles

Traefik exposes the technical consoles behind two middlewares, defined on the `traefik` service:

- `admin-allowlist`: only the IP ranges of `ADMIN_ALLOWED_SOURCE_RANGES` (CIDR, comma-separated) get
  through, the others get a 403;
- `admin-console`: `admin-allowlist` followed by the basic auth `admin-secured`
  (`ADMIN_BASIC_AUTH_USER`, generated with `htpasswd -nb admin 'my-secret-password'`).

| Console                                                                                             | Middleware        |
|-----------------------------------------------------------------------------------------------------|-------------------|
| Traefik, MinIO console, pgAdmin, phpMyAdmin, Mailpit, Grafana, Kibana (report), Sockudo `/usage`, webhook endpoint | `admin-console`   |
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

## Logs

Install ELK stack to get report-api logs available in Kibana.

```bash
# set COMPOSE_FILE=docker-compose.yml:docker-compose.elk.yml
dc up -d
```

## Next steps

If you are a developer, follow the [dev setup guide](02_dev.md)
