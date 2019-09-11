# Phraseanet services

Welcome to the mono-repository of Phraseanet micro-services!
This repository contains all the services to facilitate development but each group of services can be deployed alone.

## Setup (with docker-compose)

* Pull this repository

* If you need a fresh version of images, build all images:
```bash
bin/build.sh
```

* Run (magical) configuration for all projects:
```bash
bin/install.sh
```

* Read group of services documentation to customize environment variables:
    * [auth](./auth/README.md)
    * [uploader](./uploader/README.md)
    * [expose](./expose/README.md)

* Start the whole stack:
```bash
bin/start.sh
```

If one of the port is already allocated, see the [Changing ports](#changing-ports) section and run `bin/start.sh` again.

## Development

The `bin/start.sh` script avoid using the `docker-compose.override.yml`.
In development, we need to use it so run:
```bash
docker-compose up -d
```

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
