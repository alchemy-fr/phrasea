/**
 * Teardown of bin/dev/test-indexer-e2e.sh: removes the workspace the run
 * created. Runs even when the verification failed, and is a no-op when there
 * is nothing left to remove.
 */

import {describe, expect, it} from 'vitest';
import * as databox from './databox';
import {SLUG} from './env';

describe('cleanup', () => {
    it('removes the workspace', async () => {
        const workspace = await databox.findWorkspace();

        if (workspace) {
            await databox.deleteWorkspace(workspace.id);
        }

        expect(await databox.findWorkspace(), `workspace "${SLUG}"`).toBeNull();
    }, 60_000);
});
