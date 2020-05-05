# Infra operating tasks

## Initialization (aka migration-0)

The first time the stack is deployed we need to initialize some things:
- Create databases
- Add vhosts & permissions to rabbitmq
- etc.

All the operations are described in [`/bin/setup.sh`](../bin/setup.sh).

## Client configuration

Many containers require the [`/configs/config.json`](../configs/config.json) to be mounted inside itself.
This file is parsed and compiled during the container boot (container's entrypoint actually) in order to gain performance.
Thus, after any change in this file, the container must be restarted (or recreated) to take the new configuration into account.
This is the same behaviour as the environment variable change. 
