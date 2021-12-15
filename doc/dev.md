# Development setup

## Prerequisites

You have read [setup guide](./setup.md)

Install `mkcert`:

```bash
sudo apt-get install wget libnss3-tools
wget https://github.com/FiloSottile/mkcert/releases/download/v1.4.3/mkcert-v1.4.3-linux-amd64
sudo mv mkcert-v1.4.3-linux-amd64 /usr/bin/mkcert
sudo chmod +x /usr/bin/mkcert
```

Create your root CA for dev:

```bash
mkcert -install # If never done before
bin/dev/make-cert.sh
```

## Steps

### Add dev DNS to your host

Add the following entries to your `/etc/hosts` file:

```
sudo PHRASEA_DOMAIN=phrasea.local bin/append-etc-hosts.sh
```

### Configure env vars

Configure your local env var:
```dotenv
APP_ENV=dev
DEV_MODE=true
COMPOSE_FILE=docker-compose.yml:docker-compose.dev.yml
COMPOSE_PROFILES=...
VERIFY_SSL=false
```

```bash
# bin/build.sh optimize build order in order to maximize benefit of docker layer caching: 
bin/build.sh

# Build the dev container
dc build dev

# Install app dependencies
bin/install-dev.sh

# Start the stack
dc up -d
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

## Further reading

- [Debug application with XDEBUG](./doc/xdebug.md)
- [Deploy to minikube](./doc/minikube.md)
