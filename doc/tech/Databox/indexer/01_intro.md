---
title: 'Databox Indexer'
---

# Databox Indexer

The Databox Indexer is a tool for importing (indexing) data into Databox from various source locations. It supports different location types and provides both production and development workflows.

## Key Features
- Index data from multiple source types (e.g., Phraseanet)
- Watch locations for changes and re-index as needed
- List all configured locations
- Flexible configuration via JSON files

## Usage via Console

Use the following commands to interact with the indexer:

First, you need to run:

```bash
dc run --rm databox-indexer pnpm build
```

```bash
# Index a location
dc run --rm databox-indexer index <location-name>

# Watch a location for changes
dc run --rm databox-indexer watch <location-name>

# List all locations
dc run --rm databox-indexer list
```

## Development Workflow

For development, you can use the following commands:

```bash
dc run --rm databox-indexer pnpm build
```

```bash
# Index a location in dev mode
dc run --rm databox-indexer pnpm build && dc run --rm databox-indexer pnpm dev index <location-name>
```

Or open a shell for advanced usage:

```bash
dc run --rm databox-indexer bash
# Inside the container:
pnpm dev <command-name>
```

## Configuration

Each location is described in a `config.json` file and referenced by its `name` property. You can override the configuration file by setting the `INDEXER_CONFIG_FILE` variable in your `.env.local`. The custom configuration file should be placed in the `databox/indexer/config` directory.

### Supported Location Types

- `phraseanet`: [See Phraseanet configuration details](./conf_phraseanet.md)
