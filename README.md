# Upload service

Upload service is part of the Alchemy ecosystem.
Its role is to handle uploaded assets by authenticated users and trigger other services so they can fetch the file.

This repository contains many end projects:
- API (back end)
- Uploader client (front end)

## Setup

```bash
docker-compose -f docker-compose.yml up
```

Client web app is available at `http://localhost:8080`

## Development

```bash
docker-compose up
```

