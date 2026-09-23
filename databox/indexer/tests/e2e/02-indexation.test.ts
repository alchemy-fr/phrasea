/**
 * Step 3 of bin/dev/test-indexer-e2e.sh: what the indexation produced in
 * databox, read through the databox API.
 *
 * The fixtures were provisioned and the databox-indexer container was run
 * before this file; it only reads the result.
 */

import {writeFileSync} from 'fs';
import {beforeAll, describe, expect, it} from 'vitest';
import * as databox from './databox';
import * as fixtures from './fixtures';
import {SNAPSHOT, SRC_DIR, runLog} from './paths';
import {expectNoLoggedError, expectSet, readRunLog} from './expect';
import {SLUG} from './env';

let log: string;
let workspace: databox.Workspace;
let tree: databox.CollectionTree;
let assets: databox.Asset[];

const referenceCollections = () =>
    assets.map(
        asset =>
            `${databox.sourceUrlOf(asset)}|${
                tree.pathOf[asset.referenceCollection?.id ?? ''] ?? '<none>'
            }`
    );

const membership = () =>
    assets.flatMap(asset =>
        asset.collections.map(
            collection =>
                `${databox.sourceUrlOf(asset)}|${
                    tree.pathOf[collection.id] ?? '<none>'
                }`
        )
    );

describe('after the first indexation', () => {
    beforeAll(async () => {
        log = readRunLog(runLog(1));

        // The listings are served from Elasticsearch, which is fed from the
        // writes the indexer just made, so the workspace and then the assets
        // are given a moment to show up.
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

    it('created the workspace', () => {
        expect(workspace.slug).toEqual(SLUG);
    });

    it('built the collection tree', () => {
        expectSet(tree.paths, fixtures.expectedCollectionPaths());
    });

    it('created one asset per file, in the right reference collection', () => {
        expectSet(referenceCollections(), fixtures.expectedAssets(SRC_DIR));
    });

    it('registered the collection membership', () => {
        // Distinct from the assertion above: referenceCollection carries the
        // permission inheritance, `collections` the membership.
        expectSet(membership(), fixtures.expectedAssets(SRC_DIR));
    });

    it('indexed no hidden file and no hidden directory', () => {
        expect(
            tree.paths.filter(path => /\/\./.test(path)),
            'collections for hidden directories'
        ).toEqual([]);

        expect(
            assets
                .map(databox.sourceUrlOf)
                .filter(url => /\/\.[^/]*(\/|$)/.test(url)),
            'assets for hidden files'
        ).toEqual([]);
    });

    it('left no soft-deleted asset behind', async () => {
        const all = await databox.getAssets(workspace.id, {
            includeDeleted: true,
        });

        expect(all, 'assets including soft-deleted ones').toHaveLength(
            fixtures.FILES_INDEXED.length
        );
    });

    it('walked the blacklisted files and then filtered them out', () => {
        // They must have been seen and rejected, not simply never reached.
        for (const key of fixtures.blacklistedKeys()) {
            expect(log, `blacklist log line for ${key}`).toContain(
                `"${key}" matches blacklist, skipping`
            );
        }
    });

    it('records the state the second pass compares against', () => {
        // Handed to 03-idempotence through the shared volume: the two passes
        // are separate vitest runs, with an indexation in between.
        const snapshot = {
            collectionIds: tree.pathOf,
            assetIds: {} as Record<string, string>,
            sourceIds: {} as Record<string, string | undefined>,
        };

        for (const asset of assets) {
            const url = databox.sourceUrlOf(asset);
            snapshot.assetIds[url] = asset.id;
            snapshot.sourceIds[url] = asset.source?.id;
        }

        writeFileSync(SNAPSHOT, JSON.stringify(snapshot));
    });
});
