/**
 * Step 1 of bin/dev/test-indexer-e2e.sh: provision the fixtures.
 *
 * Writes the fixture tree and the indexer config into the shared /e2e volume
 * and removes any workspace a previous run left behind. The `ownerId` is
 * resolved here and written into the config, so the indexer container needs
 * nothing but CONFIG_FILE and --workdir /e2e.
 */

import {mkdirSync, readFileSync, rmSync, writeFileSync} from 'fs';
import path from 'path';
import {beforeAll, describe, expect, it} from 'vitest';
import * as databox from './databox';
import * as fixtures from './fixtures';
import {CONFIG_DIR, CONFIG_FILE, SRC_DIR} from './paths';
import {buildIndexerConfig} from './indexerConfig';
import {SLUG} from './env';

let ownerId: string;

describe('provisioning', () => {
    beforeAll(async () => {
        ownerId = await databox.getOwnerId();
    }, 30_000);

    it('authenticates as the indexer service account', () => {
        expect(ownerId, 'sub claim of the access token').toMatch(
            /^[0-9a-f-]{36}$/
        );
    });

    it('writes the fixture tree', () => {
        rmSync(SRC_DIR, {recursive: true, force: true});

        for (const relativePath of [
            ...fixtures.FILES_INDEXED,
            ...fixtures.FILES_BLACKLISTED,
        ]) {
            const abs = path.join(SRC_DIR, relativePath);
            mkdirSync(path.dirname(abs), {recursive: true});
            writeFileSync(abs, fixtures.fileContent(relativePath));
        }

        for (const relativePath of fixtures.FILES_INDEXED) {
            expect(
                readFileSync(path.join(SRC_DIR, relativePath), 'utf8')
            ).toEqual(fixtures.fileContent(relativePath));
        }
    });

    it('writes the indexer config', () => {
        mkdirSync(CONFIG_DIR, {recursive: true});
        writeFileSync(
            path.join(CONFIG_DIR, CONFIG_FILE),
            JSON.stringify(buildIndexerConfig(ownerId), null, 4)
        );

        const written = JSON.parse(
            readFileSync(path.join(CONFIG_DIR, CONFIG_FILE), 'utf8')
        );
        expect(written.databox.ownerId).toEqual(ownerId);
        expect(written.locations[0].options.workspaceSlug).toEqual(SLUG);
    });

    it('removes the workspace a previous run may have left', async () => {
        const stale = await databox.findWorkspace();

        if (stale) {
            await databox.deleteWorkspace(stale.id);
        }

        expect(await databox.findWorkspace(), `workspace "${SLUG}"`).toBeNull();
    }, 60_000);
});
