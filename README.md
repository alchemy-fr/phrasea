# Phrasea services

Welcome to the mono-repository of Phrasea services!
This repository contains all the services to facilitate development but each group of services can be deployed alone.

## Setup

- [Installation guide](./doc/setup.md)

## Development

- [Setup guide for development](./doc/dev.md) (requires [setup](./doc/setup.md) first)

## RabbitMQ Management

Access `https://rabbitmq-console.phrasea.local`

## Running multiple instances of services

You may need to deploy to different expose services (with their specific network/security rules).
In that case, you need to assign a unique `APP_ID` to each instance. This `APP_ID` will be stored in report service.

# Further reading

- [Private/public networks](./doc/networks.md)
- [S3 Storage](./doc/storage/s3.md)
- [Cloud Front](./doc/storage/cloudfront.md)
- [Backup and Restore](./doc/backup-restore.md)
- [Permissions](./doc/permissions.md)

## Infra

- [Operating tasks](./doc/infra-operating-tasks.md)

## Logs

Install ELK stack to get report-api logs available in Kibana.

```bash
# set COMPOSE_FILE=docker-compose.yml:docker-compose.elk.yml
dc up -d
```
