---
title: 'Elasticsearch populate'
---

# Elasticsearch populate

A populate (`fos:elastica:populate`, or "Add Populate" in the admin, which queues an
`ESPopulate` message run in a child process by `ESPopulateHandler`) rebuilds an index from
the database. FOS Elastica creates a new physical index (`<prefix><index>_<env>_<date>`),
copies the entities into it page by page, then switches the alias (`<prefix><index>_<env>`)
to it and deletes the old one. On a large instance, this takes hours.

## Writes made during a populate

Meanwhile, the searches and the live writes go through the alias, to the old index: an
ACE granted or revoked, an asset deleted or moved to the trash, a document reindexed… A
document already copied to the new index (or read from the database before the change and
copied after it) would come back with its former rights after the switch.

While the populate lock of an index is held, every live write to it is therefore journaled
in Redis, before being sent (`PopulateWriteJournal`):

- the ids of the documents indexed or deleted one by one (`IndexPersister`, through
  `Alchemy\ESBundle\Indexer\IndexWriteJournalInterface`): set `es_populate_dirty_<prefix><index>`;
- the `update_by_query` / `delete_by_query` requests (`ElasticSearchClient`, `IndexCleaner`):
  list `es_populate_dirty_queries_<prefix><index>`, in their order.

Right after the switch of the alias, the populate moves the journal aside, releases its lock
and replays it on the new index (`PopulateWriteReplayer`): the queries first (scripts that set
a value), then the documents, reindexed from the database through the usual `ESIndex`
message, which have the last word. The counts are recorded in the stats of the pass
(`replayedQueries`, `replayedDocuments`).

Guarantee: a write made before the switch is either in the database the populate copies, or
replayed; a write made after the switch reaches the new index through the alias (the journal
is written before the request, so a write it misses can only be sent after the switch). The
only window left is between the switch and the end of the replay, where a replayed query may
land after a newer write to the same documents; the documents reindexed from the database
do not have this issue.

## Lock, owner and heartbeat

Two populates of the same index never run at once: `PopulatePassListener` takes a Redis lock
per index (`es_populate_<prefix><index>`, see `PopulateLockManager`). Next to it, the owner of
the lock is recorded (`es_populate_owner_<prefix><index>`: run id, pass id, host, pid) with a
heartbeat, renewed at every step of a page and every 30 s while its documents are transformed.
The lock lives 30 minutes after the last heartbeat, so a killed worker frees the index by itself.

- Releasing a lock by hand (`app:es:populate-unlock`, or "Release lock" in the "Add Populate"
  admin page) is refused while the heartbeat of its owner is less than 5 minutes old, unless
  forced (`--even-if-alive`, "even if alive" box).
- A populate that loses its lock stops at its next heartbeat and deletes the index it was
  building.
- When the child process of `ESPopulateHandler` dies, only the passes of its own run are
  closed (`closeDeadPasses`, by run id), and a lock is only removed if this run owns it: a
  populate of one index started from the admin meanwhile keeps running.

## No index created by a write

Elasticsearch creates a missing index on the first document written to it, with a dynamic
mapping (`users` / `groups` in `text`: the ACL filters stop matching). Hence:

- the orphan indices a populate removes before starting never include an index still being
  built by a live populate (`es_populate_building_<physical index>`, renewed by its heartbeat);
- a failed or canceled populate only deletes its own index, never the one another populate is
  building;
- before every page, the populate checks that its index still exists, and stops otherwise;
- before the switch of the alias, it checks that the index carries the mapping it created (the
  `_meta.model` of FOS Elastica, and the configured type of every field): otherwise the alias
  is not switched, the pass ends in error and the index is deleted.
