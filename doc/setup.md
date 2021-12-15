# Setup (with docker-compose)

## Using a env.local (custom .env)

It may be easier to deal with a local file to manage our env variables.

You can add your `env.local` at the root of this project and define a command function in your `~/.bashrc`:

```bash
# ~/.bashrc or ~/.zshrc
function dc() {
    if [ -f env.local ]; then
        env $(cat env.local | grep -v '#' | tr '\n' ' ') docker-compose $@
    else
        docker-compose $@
    fi
}
```

## Installation

* Pull this repository

* If you need a fresh version of images, build all images:
```bash
bin/build.sh
```

* Run (magical) configuration for all projects:
```bash
bin/setup.sh
```

If the stack is already deployed, you should use migrate after a fresh build:
```bash
bin/migrate.sh
```

### Using fixtures

You may want to popupate databases with a set of fixtures:
```bash
# Be careful! This will empty the databases, insert fixtures and run bin/setup.sh again
bin/install-fixtures.sh
```

* Read group of services documentation to customize environment variables:
    * [auth](./auth/README.md)
    * [notify](./notify/README.md)
    * [notify](./databox/README.md)
    * [uploader](./uploader/README.md)
    * [databox](./databox/README.md)
    * [expose](./expose/README.md)

* Start the whole stack:
```bash
dc up -d
```

## Next:

If you are a developper: follow [dev setup guide](./dev.md)
