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
`scheduler_*` transports (one process per schedule), while the `*-worker`
services only consume the RabbitMQ queues.

Keep **exactly one replica** of each scheduler container and never scale them:
scale the `*-worker` services instead.

| Application | Schedule      | Task                                                                                         | Frequency    |
|-------------|---------------|----------------------------------------------------------------------------------------------|--------------|
| databox     | `automation`  | trigger the due automations of the saved searches                                            | every minute |
| databox     | `maintenance` | `alchemy:notifier:digest:flush` (notification digests whose window has elapsed)              | hourly       |
| databox     | `maintenance` | `app:integration:renew-tokens` (integration tokens about to expire)                          | hourly       |
| databox     | `maintenance` | `app:file:unblock-analyses` (stuck file analyses, see `doc/tech/Databox/FileAnalyzers.mdx`) | hourly       |
| databox     | `maintenance` | `app:trash:empty`                                                                            | daily        |
| uploader    | `maintenance` | `app:asset:clean-expired` (retention `ASSET_DAYS_RETENTION`, 31 days by default)             | daily        |

Hourly and daily tasks start at a fixed minute derived from the task (daily
ones between 00:00 and 02:59), to spread the load. The commands can still be
run by hand; `bin/console app:automation:run-due` triggers the due automations
once, for development.

Only Zippy still needs a host cron: when the `zippy` profile is enabled and the
stack is deployed with docker-compose on a single host, run
`bin/ops/cron-script-daily.sh` **every day**.

### Changing ports

You can change service ports by overriding the environment variables (see the `.env` file).

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
