# Databox Indexer

## Using console

```bash
# Index a location
dc run --rm databox-indexer pnpm build && dc run --rm databox-indexer index <location-name>
# Watch a location
dc run --rm databox-indexer pnpm build && dc run --rm databox-indexer watch <location-name>
# List locations
dc run --rm databox-indexer pnpm build && dc run --rm databox-indexer list
```

## Dev

```bash
dc run --rm databox-indexer bash
node@51341e79df22:/srv/workspace/databox/indexer$ pnpm dev <command-name>
```

### cheat
#### add a lib

```bash
dc run --rm dev zsh
root@local ➜  indexer  cd databox/indexer
root@local ➜  indexer  pnpm i --save-dev twig
root@local ➜  indexer  pnpm i --save-dev @types/twig
```
