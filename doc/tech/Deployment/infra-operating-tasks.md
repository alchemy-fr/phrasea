# Infra operating tasks

## Initialization (aka migration-0)

The first time the stack is deployed we need to initialize some things:
- Create databases
- Add vhosts & permissions to rabbitmq
- etc.

All the operations are described in [`/bin/setup.sh`](@phrasea-repo/bin/setup.sh).
