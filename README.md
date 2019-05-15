# Upload service

Upload service is part of the Alchemy ecosystem.
Its role is to handle uploaded assets by authenticated users and trigger other services so they can fetch the file.

This repository contains many end projects:
- Upload API (back end)
- Uploader client (front end)
- Auth API (back end)

## Setup

```bash
bin/build.sh
```

Override environment variables defined in `.env` file:

```bash
DEV_MODE=false
CLIENT_ID=<THE_CLIENT_ID>
CLIENT_RANDOM_ID=<A_RANDOM_HASH>
CLIENT_SECRET=<A_SECRET>
DEFAULT_USER_EMAIL=admin@alchemy.fr
DEFAULT_USER_PASSWORD=<A_PASSWORD>
```

Then run:

```bash
bin/build.sh
bin/install.sh
```

Then you can start the stack:

```bash
bin/start.sh
```

If one of the port is already allocated, see the [Changing ports](#changing-ports) section and run `bin/start.sh` again.

Client web app is available at `http://localhost`

## Development

```bash
docker-compose up -d
```

## Changing ports

You can change the services port by overriding the environment variables (see `.env` file).

## Run for customer

In development mode we can change the API URI but for some customer we may need to change the API target.
We need to define some environment variables:

```bash
DEV_MODE=false
UPLOAD_BASE_URL=<THE_UPLOAD_BASE_URL>
CLIENT_ID=<THE_CLIENT_PUBLIC_ID>
CLIENT_SECRET=<THE_CLIENT_SECRET>
```

> `UPLOAD_BASE_URL` corresponds to the upload_php service which is bound to 8080 by default.

## RabbitMQ Management

Access `http://localhost:8082` (or you can change the port with env var `$RABBITMQ_MGT_PORT`).

## Database Management

Access PgAdmin4 at `http://localhost:5050`

You can login with `admin@alchemy.fr` / `CxkngkeTRPkJOyniPHmZ` by default (see `.env` file).
Then add the server by using:
Connection > Host name: `db`
Connection > Port: `5432`
Connection > Username: `admin` # by default (see `.env` file)
Connection > Password: `3IKYHEZZn0EQbOzeEQC1` # by default (see `.env` file)

# Further reading

- [Request flow](./doc/request_flow.md)
- [Form configuration](./doc/form_config.md)
