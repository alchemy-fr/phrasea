# Setup (with docker-compose)

## Using a .env.local (custom .env)

It may be easier to deal with a local file to manage our env variables.

You can add your `.env.local` at the root of this project and define a command function in your `~/.bashrc`:

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

> NB: If one of the port is already allocated, see the [Changing ports](#changing-ports) section and run command again.

* Pull this repository

* If you need a fresh version of images, build all images:
```bash
bin/build.sh
```

### Secure your installation

Change all the default passwords or secrets you can see in `.env`.

They often start with `__CHANGE_ME_`.

### Do install

* Run (magical) configuration for all projects:
```bash
bin/setup.sh
```

If the stack is already deployed, you should use migrate after a fresh build:
```bash
bin/migrate.sh
```

### Configure Let's encrypt

First, make sure you have set the `PHRASEA_DOMAIN` to your main domain. A wildcard certificate will be generated on that domain.

Add and configure the following lines to your `env.local`:

```dotenv
TRAEFIK_PROVIDERS_FILE_FILENAME=
LETS_ENCRYPT_ENABLED=true
LETS_ENCRYPT_PROVIDER=gandiv5
LEGO_GANDIV5_API_KEY=<Your API key>
```

Then just update the traefik container:
```bash
dc up -d traefik
```

and wait for traefik to grab your certificate.

By default, we are using Let's Encrypt's staging. To get a fresh production certificate, you should set:

```dotenv
LETS_ENCRYPT_CA_SERVER=https://acme-v02.api.letsencrypt.org/directory
```

### Changing ports

You can change the services port by overriding the environment variables (see `.env` file).

### Using fixtures

You may want to popupate databases with a set of fixtures:
```bash
# Be careful! This will empty the databases, insert fixtures and run bin/setup.sh again
bin/install-fixtures.sh
```

* Read group of services documentation to customize environment variables:
    * [databox](../databox/README.md)
    * [expose](../expose/README.md)
    * [report](../report/README.md)
    * [uploader](../uploader/README.md)

* Start the whole stack:
```bash
dc up -d
```

## Next:

If you are a developer: follow [dev setup guide](./dev.md)
