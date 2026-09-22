# Defects found in `databox/indexer` while writing the test suite

All of them are **fixed**. Each entry says what was wrong, what it now does, and
which test would catch a regression.

---

## 1. `streamify()` never terminated — the S3 indexer could not finish

`src/lib/streamify.ts`

`oncePromise()` only ever listened for the _data_ event and always resolved with
`{done: false}`. The `done` flag set by the `endEvent` handler was read only by
the `while` condition, which was reached only after another data event had
resolved the pending promise. A stream that ended normally left the generator
awaiting forever, so `s3AmqpIterator` hung after the last object of a bucket and
never moved on to the next one. It was masked because `index` also started an
HTTP server that kept the process alive regardless; `--no-server` turned it into
a visible hang.

**Now**: the generator waits on the value, the end **and** the error at once, so
the end of the stream finishes it and a stream error rejects instead of hanging.
A stream that was already over when the generator is first pulled is detected
through `readableEnded`, since no event is left to wait for.

`tests/unit/lib/streamify.test.ts` → _"terminates on the end event alone"_,
_"terminates on a stream that had already ended"_, _"rejects when the stream
errors"_.

## 2. An empty `s3.bucketNames` silently dropped every event

`src/handlers/s3_amqp/watcher.ts`

```ts
const bucketsList = getConfig('s3.bucketNames', '', config).split(',');
// ''.split(',') === ['']  →  length 1, so the guard below rejected everything
```

The guard reads as "if a list was configured, filter on it", but an empty value
produced `['']`, whose length is 1. Every incoming S3 event was discarded
instead of all of them being accepted.

**Now**: the entries are trimmed and the empty ones dropped, so an empty or
blank-padded `bucketNames` means "every bucket".

`tests/unit/handlers/s3_amqp/watcher.test.ts` → _"accepts every bucket when
bucketNames is empty"_, _"ignores the blank entries of a padded bucketNames"_.

## 3. `index --create-new-workspace` was a no-op for `fs` locations

`src/handlers/fs/indexer.ts`, `config/config.json`

`fsIndexer` received the command options as its fourth argument but never read
them: the flag came from the JSON config instead. So `index <loc> -n` did
nothing — and, the other way round, the `my_fs` location shipped in
`config/config.json` had `"createNewWorkspace": true`, so **every run flushed
the workspace**, whether or not the flag was passed.

**Now**: `fsIndexer` flushes when the CLI flag *or* the location option asks for
it, and the shipped sample no longer flushes.

`tests/unit/handlers/fs/indexer.test.ts` → _"takes flushExisting from the
--create-new-workspace flag"_, _"takes flushExisting from the JSON config"_,
_"does not flush when neither the config nor the flag asks for it"_.

## 4. An unset `searchOrder` made the Phraseanet client throw

`src/handlers/phraseanet/phraseanetClient.ts`

`''.split(',')` is `['']`, not `[]`, so the destructured field was `''` — never
`undefined` — and the `?? 'record_id'` fallback was dead code. A location that
omitted `searchOrder` failed to construct with
`searchOrder must be 'record_id,asc' or 'record_id,desc', got 'undefined'`.
It worked only because `.env` always sets `INDEXER_PHRASEANET_SEARCH_ORDER`.

**Now**: an unset, empty or partial `searchOrder` falls back to
`record_id,asc`; a genuinely invalid one still throws.

`tests/unit/handlers/phraseanet/phraseanetClient.test.ts` → _"falls back to
record_id,asc when searchOrder is unset"_ and the `rejects %p` table.

## 5. `getAlternateUrls()` broke on a pattern with two placeholders

`src/alternateUrl.ts`

```ts
c.pathPattern.replace(/\${(.+)}/g, ...)   // greedy
```

`.+` is greedy, so `"${path}-${sourcePath}"` captured `path}-${sourcePath` — not
a key of the dictionary — and the whole URL became the string `"undefined"`.
Only single-placeholder patterns worked.

**Now**: `/\$\{([^}]+)\}/g`, so each placeholder is substituted on its own.

`tests/unit/alternateUrl.test.ts` → _"substitutes each placeholder of a
two-placeholder pattern"_.

## 6. `forceArray(null)` threw instead of passing null through

`src/lib/utils.ts`

`typeof null === 'object'`, so null took the `Object.keys(object)` branch and
raised a `TypeError`, although the signature (`T = undefined | null`) advertises
a passthrough.

**Now**: null and undefined are returned as they are, and the `@ts-expect-error`
that papered over it is gone.

`tests/unit/lib/utils.test.ts` → _"passes null through, as the signature
advertises"_.

## 7. `CPhraseanetMetadata.NullMetadata` was a shared mutable singleton

`src/handlers/phraseanet/CPhraseanetMetadata.ts`

The same instance was handed out by `getMetadata()` for every missing field, and
nothing froze it. Any caller that mutated it — pushing to `.values`, assigning
`.value` — corrupted the value seen by every other caller for the rest of the
process.

**Now**: `NullMetadata` is a getter returning a fresh instance.

`tests/unit/handlers/phraseanet/CPhraseanetMetadata.test.ts` → _"hands out a
fresh instance every time, so a mutation cannot leak"_.

## 8. The blacklist log message said the opposite of what happened

`src/pathFilter.ts`

```ts
logger.debug(`"${path}" does not match blacklist, skipping...`);
```

The path was skipped precisely _because_ it matched the blacklist. The whitelist
branch above is correctly worded, which made the copy-paste obvious.

**Now**: `"…" matches blacklist, skipping...`. The e2e suite greps for that
string to prove the blacklisted files were walked and then filtered, so
`tests/e2e/02-indexation.test.ts` follows it.

`tests/unit/pathFilter.test.ts` → _"rejects a blacklisted path even when
whitelisted"_.

## 9. `escapePath()` only stripped half of the C0 control characters

`src/lib/pathUtils.ts`

`/[\x00-\x0F]/g` let `\x10`–`\x1F` through, including `\x1B` (escape).

**Now**: the whole C0 range, `[\x00-\x1F]`.

`tests/unit/lib/pathUtils.test.ts` → _"strips the whole C0 range, not just
\x00-\x0F"_.

## 10. `createWorkspace()` ignored the client's own `ownerId`

`src/databox/client.ts`

Every other method sent `this.ownerId`, the value the `DataboxClient` was
constructed with; `createWorkspace()` read `getStrict('databox.ownerId')` from
the global config file instead, so a client built with an explicit owner
silently created workspaces owned by someone else.

**Now**: `this.ownerId`, like everywhere else.

`tests/unit/databox/client.test.ts` → _"does not fall back to the ownerId of the
config file"_.

## 11. Every request was retried, including the non-idempotent `POST`s

`src/lib/axios.ts`

`createHttpClient()` installed `axios-retry` with `retries: 10` and a
`retryCondition` that returned `true` for anything that was not a 4xx/500
response — which includes a client-side timeout, since those carry no response
at all. Combined with `timeout: 30000`, a `POST` the API took more than 30
seconds to answer was **replayed**, and since the work had already been done the
replay hit the unique index:

```
http.WARN:  Request "POST /workspaces" failed, retrying...
http.ERROR: Error response (POST /workspaces): slug: Slug is already taken
```

The run then aborted on a message describing neither the cause (the API was
slow) nor the actual state (the workspace *was* created). The same hazard
applied to `POST /assets` and `POST /collections`.

**Now**: the condition starts with `axios-retry`'s own
`isNetworkOrIdempotentRequestError`, so only `GET`, `HEAD`, `OPTIONS`, `PUT` and
`DELETE` are replayed; the existing status exclusions still apply on top.

## 12. `package.json` pointed at files the build never produces

`main` was `dist/index.mjs`, which no build ever writes, and `bin` held
`{"console": "node dist/console.mjs"}` — a command line where npm expects a
path. Nothing used either: the container entrypoint goes through
`scripts.console`.

**Now**: `main` is `dist/console.mjs` and the malformed `bin` block is gone.

## 13. `CLAUDE.md` pointed at a deleted script

`CLAUDE.md:70` listed `bin/install-fixtures.sh`, removed by commit `1e7e1a280`.

**Now**: `bin/dev/reset-db-fixtures.sh`.
