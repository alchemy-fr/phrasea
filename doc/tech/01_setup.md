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
        docker compose --env-file=.env --env-file=.env.local $@
    else
        docker compose $@
    fi
}
```

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

### Setup cron jobs

You may want to setup cron jobs to run periodic tasks (like cleaning old data).
If you plan to deploy the stack with docker-compose on a single host machine,
you can use the provided `bin/ops/cron-script.sh` script to add cron jobs to your host machine.

### Changing ports

You can change service ports by overriding the environment variables (see the `.env` file).

### Using fixtures

You may want to populate databases with a set of fixtures:

```bash
# Warning! This will empty the databases, insert fixtures, and run bin/setup.sh again.
bin/install-fixtures.sh
```

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
