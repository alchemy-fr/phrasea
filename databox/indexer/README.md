# Databox Indexer.

To index (import) data to Databox, from different types of locations.

Each location is described in `config.json`, referenced by `name`.

```bash
# Index a location
dc run --rm databox-indexer pnpm build && dc run --rm databox-indexer index <location-name>
# Watch a location
dc run --rm databox-indexer pnpm build && dc run --rm databox-indexer watch <location-name>
# List locations
dc run --rm databox-indexer pnpm build && dc run --rm databox-indexer list
```

## Location types.

### Type = "phraseanet" : [doc/conf_phraseanet](doc/conf_phraseanet.md)



----
## Dev.

```bash
dc run --rm databox-indexer bash
node@51341e79df22:/srv/workspace/databox/indexer$ pnpm dev <command-name>
```

### dev cheat...

- add a lib
```bash
dc run --rm dev zsh
root@local ➜  workspace cd databox/indexer
root@local ➜  indexer pnpm i --save-dev twig

```

