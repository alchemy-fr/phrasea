# Development setup

## Prerequisites

You have read [setup guide](./setup.md)

*mkcert* is installed.

Install `mkcert`:

```bash
sudo apt-get install wget libnss3-tools
wget https://github.com/FiloSottile/mkcert/releases/download/v1.4.3/mkcert-v1.4.3-linux-amd64
sudo mv mkcert-v1.4.3-linux-amd64 /usr/bin/mkcert
sudo chmod +x /usr/bin/mkcert
```

## Steps

### Create your root CA for dev

```bash
mkcert -install # If never done before
bin/dev/make-cert.sh
```

If you're installing self-signed certificate to a remote machine, get to root CA from:

```bash
cat $(mkcert -CAROOT)/rootCA.pem
```

Then add it to your browser!

### Add dev DNS to your host

```
sudo bin/dev/append-etc-hosts.sh
```

### Configure env vars

```bash
# bin/build.sh optimize build order in order to maximize benefit of docker layer caching:
bin/build.sh
```

Configure your local env var:
```dotenv
APP_ENV=dev
DEV_MODE=true
COMPOSE_FILE=docker-compose.yml:docker-compose.dev.yml
COMPOSE_PROFILES=...
VERIFY_SSL=false
```

Build the dev container and install app dependencies
```bash
dc build dev

dc run --rm dev bin/dev/composer-install.sh
dc run --rm dev pnpm install
```

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
./bin/optimize-composer-docker-cache ./databox/api

# You can update multiple projects at the same time
./bin/optimize-composer-docker-cache ./databox/api ./uploader/api
```

## Further reading

- [Debug application with XDEBUG](./xdebug.md)
- [Deploy to minikube](./minikube.md)
