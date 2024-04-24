# Databox Indexer

To index (import) data to Databox, from different types of locations.

## Using console

```bash
# Index a location
dc run --rm databox-indexer pnpm build && dc run --rm databox-indexer pnpm dev index <location-name>
# Watch a location
dc run --rm databox-indexer pnpm build && dc run --rm databox-indexer pnpm dev watch <location-name>
# List locations
dc run --rm databox-indexer pnpm build && dc run --rm databox-indexer pnpm dev list
```

## Dev

```bash
dc run --rm databox-indexer bash
node@51341e79df22:/srv/workspace/databox/indexer$ pnpm dev <command-name>
```

## Configuration
Each location is described in `config.json`, referenced by `name`.


### Location types

#### Type = "phraseanet": [doc/conf_phraseanet](doc/conf_phraseanet.md)
