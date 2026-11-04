/**
 * Step 5 of bin/dev/test-indexer-e2e.sh: the same indexation, run twice, must
 * not duplicate anything.
 *
 * Reads the snapshot 02-indexation left in the shared volume before the
 * databox-indexer container was run a second time.
 */

import {readFileSync} from 'fs';
import {beforeAll, describe, expect, it} from 'vitest';
import * as databox from './databox';
import * as fixtures from './fixtures';
import {SNAPSHOT, SRC_DIR, runLog} from './paths';
import {expectNoLoggedError, expectSet, readRunLog} from './expect';
import {SLUG} from './env';

type Snapshot = {
    collectionIds: Record<string, string>;
    assetIds: Record<string, string>;
    sourceIds: Record<string, string | undefined>;
};

let log: string;
let snapshot: Snapshot;
let workspace: databox.Workspace;
let tree: databox.CollectionTree;
let assets: databox.Asset[];

describe('after the second indexation', () => {
    beforeAll(async () => {
        log = readRunLog(runLog(2));
        snapshot = JSON.parse(readFileSync(SNAPSHOT, 'utf8')) as Snapshot;

        const found = await databox.waitFor(
            databox.findWorkspace,
            ws => null !== ws
        );
        expect(found, `workspace "${SLUG}"`).not.toBeNull();
        workspace = found!;

        assets = await databox.waitFor(
            () => databox.getAssets(workspace.id),
            list => list.length === fixtures.FILES_INDEXED.length
        );
        tree = await databox.getCollectionTree(workspace.id);
    }, 120_000);

    it('ran without logging an error', () => {
        expectNoLoggedError(log);
    });

    it('reused the assets instead of recreating them', () => {
        // Comparing ids, not source urls: equal ids prove the indexer found
        // the existing assets by key rather than deleting and recreating them.
        const ids: Record<string, string> = {};
        for (const asset of assets) {
            ids[databox.sourceUrlOf(asset)] = asset.id;
        }

        expect(ids).toEqual(snapshot.assetIds);
    });

    it('reused the collections instead of recreating them', () => {
        expect(tree.pathOf).toEqual(snapshot.collectionIds);
    });

    it('left the collection tree, the assets and the membership unchanged', () => {
        expectSet(tree.paths, fixtures.expectedCollectionPaths());

        expectSet(
            assets.map(
                asset =>
                    `${databox.sourceUrlOf(asset)}|${
                        tree.pathOf[asset.referenceCollection?.id ?? ''] ??
                        '<none>'
                    }`
            ),
            fixtures.expectedAssets(SRC_DIR)
        );

        expectSet(
            assets.flatMap(asset =>
                asset.collections.map(
                    collection =>
                        `${databox.sourceUrlOf(asset)}|${
                            tree.pathOf[collection.id] ?? '<none>'
                        }`
                )
            ),
            fixtures.expectedAssets(SRC_DIR)
        );
    });

    it('reports the known replacement of the source files', () => {
        // Known, deliberate non-idempotence: handleSource() in the databox API
        // persists a new File on every POST /assets instead of looking one up
        // by url, so asset.source points at a fresh file after the second run.
        // Reported rather than asserted as a failure.
        const replaced = assets.filter(
            asset =>
                asset.source?.id !==
                snapshot.sourceIds[databox.sourceUrlOf(asset)]
        );

        // eslint-disable-next-line no-console
        console.log(
            `    source files replaced on the second run: ${replaced.length}/${assets.length} ` +
                '(databox creates a new File row per POST)'
        );
    });
});
