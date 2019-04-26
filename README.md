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

## Build for customer

In development mode we can change the API URI but for some customer we may need to hard write the API target.
We need to build the image with the specific value as argument:

```bash
docker-compose -f docker-compose.yml build \
    --build-arg UPLOAD_BASE_URL=https://upload.my-customer.com \
    # Disable the DEV mode  which is enabled by default (hide some settings in application)
    --build-arg DEV_MODE=false \ 
    --build-arg UPLOAD_BASE_URL=<THE_UPLOAD_BASE_URL> \
    --build-arg CLIENT_ID=<THE_CLIENT_PUBLIC_ID> \
    --build-arg CLIENT_SECRET=<THE_CLIENT_SECRET> \ 
    client
```

## RabbitMQ Management

Access `http://localhost:8082` (or you can change the port with env var `$RABBITMQ_MGT_PORT`).
