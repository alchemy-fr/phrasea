# Message deduplication

Some Messenger messages leave in bursts with the same payload: an asset flushed by every job of
its ingestion workflow, the ACEs of a workspace edited one after the other, assets added one by
one to a basket… Each copy would trigger the same full reindexation or heavy processing. They
carry a Symfony `DeduplicateStamp`: the `DeduplicateMiddleware` (on every bus, since
`framework.lock` is enabled) takes a non-blocking Redis lock on the stamp key when the message is
dispatched, and **silently drops the message if the lock is already held**.

## Two modes

`App\Consumer\MessageDeduplication` builds the envelopes, with the key
`dedup:<message class>:<sha1 of the serialized message>`: only strictly identical messages (same
IDs, same options) are merged.

| Helper | Lock released | Effect |
|---|---|---|
| `coalesce($message, $ttl = 3600)` | when the message is received, before its handler runs | at most one copy waits in the queue; a message dispatched while the previous one is handled is queued |
| `untilHandled($message, $ttl = 900)` | once the message is handled (or fails without retry) | no identical message while one is queued **or being handled**; a change made during the handling is lost |

The TTL frees the lock of a message that never reaches a worker (queue purged…).

```php
$this->bus->dispatch(MessageDeduplication::coalesce(new IndexAllAssets($workspaceId)));
$this->postFlushStack->addBusMessage(MessageDeduplication::coalesce(new BasketUpdate($id), 900));
```

## When to use it

- the payload of the duplicates is identical (IDs only, no list of changes);
- the handler reads the current state (database, Elasticsearch) instead of a context of the
  sender: running it once for N dispatches gives the same result. `untilHandled()` only when the
  result depends on immutable inputs only (e.g. the analysis of a file);
- the message is routed to a transport (`#[MessengerMessage]` or `messenger.yaml` routing). A
  message handled synchronously never gets a `ReceivedStamp`: its lock would be held until the TTL
  and the identical messages dispatched meanwhile would be lost.

## Messages concerned

| Message | Mode, TTL |
|---|---|
| `ESIndex` (es-bundle `SearchIndexer`, option `alchemy_es.deduplication_ttl`, `0` disables it) | coalesce, 900 s |
| `IndexAllAssets`, `IndexAllCollections`, `ComputeCollectionBranch`, `ComputeWorkspaceCollections`, `IndexCollectionBranch`, `IndexCollectionAssets`, `AttributeEntityUpdate` | coalesce, 3600 s |
| `SyncBasket`, `BasketUpdate`, `BuildDynamicRendition`, `FaceRecognitionPhoto`, `FaceRecognitionAnalyze` | coalesce, 900 s |
| `RekognitionAnalyze` (paid AWS call) | until handled, 900 s |
| `BuildStoryContactSheet` (`StoryContactSheetScheduler`, its own key per story) | coalesce, delay + 60 s |

## Tests

The test environment uses an `in-memory` lock store (`config/packages/lock.yaml`): the messages
intercepted by the test transports would otherwise keep their locks in Redis from one test, and one
run, to another. The kernel being rebooted for each test, the store starts empty.
