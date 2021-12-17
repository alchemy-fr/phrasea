# Phrasea services

Welcome to the mono-repository of Phrasea micro-services!
This repository contains all the services to facilitate development but each group of services can be deployed alone.

## Setup (with docker-compose)

### Using a env.local (custom .env)

It may be easier to deal with a local file to manage our env variables.

You can add your `env.local` at the root of this project and define a command function in your `~/.bashrc`:

```bash
# ~/.bashrc or ~/.zshrc
function dc() {
    if [ -f env.local ]; then
        env $(cat env.local | grep -v '#' | tr '\n' ' ') docker-compose $@
    else
        docker-compose $@
    fi
}
```

### Installation

* Pull this repository

* If you need a fresh version of images, build all images:
```bash
bin/build.sh
```

* Run (magical) configuration for all projects:
```bash
bin/setup.sh
```

If the stack is already deployed, you should use migrate after a fresh build:
```bash
bin/migrate.sh
```

#### Using fixtures

You may want to popupate databases with a set of fixtures:
```bash
# Be careful! This will empty the databases, insert fixtures and run bin/setup.sh again
bin/install-fixtures.sh
```

* Read group of services documentation to customize environment variables:
    * [auth](./auth/README.md)
    * [notify](./notify/README.md)
    * [uploader](./uploader/README.md)
    * [databox](./databox/README.md)
    * [expose](./expose/README.md)

* Start the whole stack:
```bash
docker-compose up -d
```

#### Run SAML test providers

```bash
docker-compose -f docker-compose.saml.yml up -d
```

If one of the port is already allocated, see the [Changing ports](#changing-ports) section and run `docker-compose up -d` again.

## Development

Configure your local env var:
```dotenv
APP_ENV=dev
DEV_MODE=true
COMPOSE_FILE=docker-compose.yml:docker-compose.dev.yml
VERIFY_SSL=false
```

```bash
# bin/build.sh optimize build order in order to maximize benefit of docker layer caching: 
bin/build.sh

# Build the dev container
docker-compose build dev

# Install app dependencies
bin/install-dev.sh

# Start the stack
docker-compose up -d
```

### Shared libraries

Back applications share some librairies/bundles (stored in this repository).
Because symlinking does not work outside a Docker container, we need to copy the bundles in the volume of the container.
When modifying a local bundle, we need to keep it synced with:

```bash
bin/update-libs.sh
```

This will copy all librairies/bundles (`./lib/*`) in all Symfony application in a sub folder `__lib`.

### Composer caching in Docker

In order to keep vendor docker layer and to prevent composer from downloading all packages every time an app file change
we use a step (docker layer) for composer install a warm composer cache before copying all app files.
We keep a `composer.json` and `composer.lock` version isolated (in ./docker/caching of each PHP service).

You can update both this two  files in order to keep a fresh cache.
Because local packages (repositories with type=path) will neither be copied nor cached, we need to remove them from the requirements.

You can use the following helper to do so automatically:
```bash
# Usage
# optimize-composer-docker-cache [app-path]
./bin/optimize-composer-docker-cache ./auth/api

# You can update multiple projects at the same time
./bin/optimize-composer-docker-cache ./auth/api ./uploader/api
```

## Changing ports

You can change the services port by overriding the environment variables (see `.env` file).

## RabbitMQ Management

Access `http://localhost:8182` (or you can change the port with env var `$RABBITMQ_MGT_PORT`).

## Database Management

Access PgAdmin4 at `http://localhost:8190`

You can login with `admin@alchemy.fr` / `CxkngkeTRPkJOyniPHmZ` by default (see `.env` file).
Then add the server by using:

Connection > Host name: `db`

Connection > Port: `5432`

Connection > Username: `alchemy` # by default (see `.env` file)

Connection > Password: `3IKYHEZZn0EQbOzeEQC1` # by default (see `.env` file)

## Running multiple instances of services

You may need to deploy to different expose services (with their specific network/security rules).
In that case, you need to assign a unique `APP_ID` to each instance. This `APP_ID` will be stored in report service.

# Further reading

- [Private/public networks](./doc/networks.md)
- [S3 Storage](./doc/storage/s3.md)

## Dev

- [Local NGINX](./doc/dev-with-nginx.md)
- [Configure SSL](./doc/configure-ssl.md)
- [Debug application with XDEBUG](./doc/xdebug.md)
- [Deploy to minikube](./doc/minikube.md)

## Infra

- [Operating tasks](./doc/infra-operating-tasks.md)

## Logs

Install ELK stack to get report-api logs available in Kibana.

```bash
docker-compose -f docker-compose.elk.yml up -d
```
