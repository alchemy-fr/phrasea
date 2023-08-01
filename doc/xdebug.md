# Debugging PHP application with Xdebug

You can enable Xdebug with:

```bash
export XDEBUG_ENABLED=1
docker-compose up -d
```

Remote host is fixed because of the subnet network from compose.

You need to configure file mapping in your IDE.

Each application should have its server configured in PhpStorm.
Each server name should follow the following pattern: `docker-server-SERVICE` (e.g. `docker-server-databox`).
Then you need to enable path mappings for the server. See the example below:

![PhpStorm mapping](./xdebug-php-storm.png)

> Configure the `Absolute path on the server` to `/srv/app` at the application project path (e.g. `~/projects/phrasea/databox/api` in this case).

For the uploader application you would have:
- a server named `docker-server-uploader` in PhpStorm
- set the path mapping: `~/projects/phrasea/uploader/api` ->  `/srv/app`

## Debugging commands

```bash
XDEBUG_ENABLED=1 dc run --rm dev
cd databox/api
export XDEBUG_SESSION="phpstorm"
sf app:command
```
