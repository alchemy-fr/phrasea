---
title: 'Development Setup'
---

# Development Setup

## Prerequisites

* You have read [setup guide](01_setup.md)

* *mkcert* is installed.

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

dc run --rm dev bin/dev/install.sh
dc run --rm dev pnpm install
```

### Composer caching in Docker

The PHP API images (databox, expose, uploader) install their dependencies in a dedicated layer before the code is copied,
so that a code change does not make Composer download every package again.
This layer only copies `composer.json` and `composer.lock`, removes the local packages (path repositories to `lib/php`) from them
with `composer-strip-path-packages` (shipped in the `php-fpm-base` image) and runs `composer install` to fill the Composer cache.
The original files are then copied with the rest of the code and the final `composer install` extracts the packages from the cache.

The layer is rebuilt whenever `composer.json` or `composer.lock` changes, there is nothing to maintain by hand.

## Further reading

- [Debug application with XDEBUG](Development/xdebug.md)
- [Deploy to minikube](Deployment/minikube.md)
