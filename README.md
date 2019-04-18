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

Client web app is available at `http://localhost`

## Development

```bash
docker-compose up
```

## Build for customer

In development mode we can change the API URI but for some customer we may need to hard write the API target.
We need to build the image with the specific value as argument:

```bash
docker-compose -f docker-compose.yml build \
    --build-arg UPLOAD_BASE_URL=https://upload.my-customer.com \
    # Disable the DEV mode  which is enabled by default (hide some settings in application)
    --build-arg DEV_MODE=false \ 
    client
```
