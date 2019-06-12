# Upload service

Upload service is part of the Alchemy ecosystem.
Its role is to handle uploaded assets by authenticated users and trigger other services so they can fetch the file.

This repository contains many end projects:
- Upload API (back end)
- Uploader client (front end)
- Auth API (back end)

## Setup

Pull this repository

### Build

If you need a fresh version of images, build all images:
```bash
bin/build.sh
```

### Create "uploader" application on Phraseanet

- Create new application (i.e `uploader`) at https://<alpha.preprod.alchemyasp.com>/developers/application/new/
- Generate a token

> Note that the token will be link to your user account. Uploader will reach Phraseanet through your user.

> **TODO:** Phraseanet should provide an API key instead of a user OAuth token.

Override environment variables defined in `.env` file:

```bash
DEV_MODE=false
CLIENT_ID=<THE_CLIENT_ID> # NOT the client ID from Phraseanet
CLIENT_RANDOM_ID=<A_RANDOM_HASH>
CLIENT_SECRET=<A_SECRET> # NOT the client secret from Phraseanet
DEFAULT_USER_EMAIL=admin@alchemy.fr
DEFAULT_USER_PASSWORD=<A_PASSWORD>
AUTH_BASE_URL=https://auth.uploader.com
PHRASEANET_BASE_URL=https://alpha.preprod.alchemyasp.com
PHRASEANET_ACCESS_TOKEN=<THE_TOKEN_GOT_FROM_PHRASEANET_APPLICATION>
MAILER_URL=smtp://username:password@provider:25
```

### Installation (DB/RabbitMQ setup)

```bash
bin/install.sh
```

## Start

```bash
bin/start.sh
```

If one of the port is already allocated, see the [Changing ports](#changing-ports) section and run `bin/start.sh` again.

Client web app is available at `http://localhost`

## Development

```bash
docker-compose up -d
```

In order to avoid Phraseanet enqueue request, you can set the `PHRASEANET_ACCESS_TOKEN` env to `avoid`.

## Changing ports

You can change the services port by overriding the environment variables (see `.env` file).

## RabbitMQ Management

Access `http://localhost:8082` (or you can change the port with env var `$RABBITMQ_MGT_PORT`).

## Database Management

Access PgAdmin4 at `http://localhost:5050`

You can login with `admin@alchemy.fr` / `CxkngkeTRPkJOyniPHmZ` by default (see `.env` file).
Then add the server by using:
Connection > Host name: `db`
Connection > Port: `5432`
Connection > Username: `alchemy` # by default (see `.env` file)
Connection > Password: `3IKYHEZZn0EQbOzeEQC1` # by default (see `.env` file)

# Further reading

- [Dev with NGINX](./doc/dev-with-nginx.md)
- [Request flow](./doc/request_flow.md)
- [Form configuration](./doc/form_config.md)
- [Form steps](./doc/form-steps.md)
