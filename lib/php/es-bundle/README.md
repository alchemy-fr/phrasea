# Alchemy ES Bundle

Elasticsearch integration shared by the Phrasea APIs (indexing, deferred index listener, messenger handler).

## Console commands

- `alchemy:es:debug-index [-i <index>]` — print settings, mappings and aliases of the physical index behind each logical FOS Elastica index (read-only).
- `alchemy:es:delete-index [-i <index>] [--remove-olds|--olds-only] [--force-prefix] [--force]` — remove physical indices. The plan is displayed and must be confirmed (`--force` skips the prompt and is required in non-interactive mode).

## EasyAdmin screens

When `easycorp/easyadmin-bundle` is installed, the bundle registers an admin page at `/<dashboard>/elasticsearch` (route `easyadmin_es_index`) to manage the cluster:

- logical indices: physical index behind each one, document count, old generations, **reset/create** (recreate empty with the configured mapping) and **remove old indices**;
- physical indices: health, status, docs, size, shards, aliases; **refresh**, **close/open**, **delete**;
- aliases: **add**, **remove**, and **switch** (atomically point an alias to a single index, e.g. to roll back to a previous generation).

Every mutation is a CSRF-protected POST confirmed through a modal.

Add the menu entry to your `DashboardController`:

```php
yield \Alchemy\ESBundle\Admin\ESAdminMenu::createMenuItem();
```
