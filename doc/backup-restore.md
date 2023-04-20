# Backup and Restore

## Backup

An export file will contain:
- all databases as .sql files
- config.json (from ./configs/)

### From docker compose

```
bin/ops/dc-export-all.sh
```

## Restore

### To docker compose

Get the `*.tar.gz` backup file of your backup and place it on the server where the target phrasea instance is installed.
Go to the root folder of your targetted phrasea project, then simply run:

```
bin/ops/dc-import-all.sh /path/to/phrasea-2022-10-26-15-48.tar.gz
```

### To Kubernetes cluster with local databases

Get the `*.tar.gz` backup file.
Then run:

```
bin/ops/import-all-k8s.sh <see usage>
```

### To a remote database (e.g. RDS)

Get the `*.tar.gz` backup file.
Then run:

```
bin/ops/import-all-remove-db.sh <see usage>
```
