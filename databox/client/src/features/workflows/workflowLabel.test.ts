import {describe, expect, it} from 'vitest';
import {workflowBaseName, workflowWorkspaceId} from './workflowLabel';

describe('workflow names', () => {
    it('splits the workflow and the workspace of a run on an asset', () => {
        expect(workflowBaseName('asset-ingest:ws-1')).toBe('asset-ingest');
        expect(workflowWorkspaceId('asset-ingest:ws-1')).toBe('ws-1');
    });

    it('has no workspace for the other workflows', () => {
        expect(workflowBaseName('incoming-uploader-file')).toBe(
            'incoming-uploader-file'
        );
        expect(workflowWorkspaceId('incoming-uploader-file')).toBeUndefined();
        expect(workflowWorkspaceId('asset-ingest:')).toBeUndefined();
    });
});
