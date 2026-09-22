import {vi} from 'vitest';
import {DataboxClient} from '../../src/databox/client';

export type FakeDataboxClient = DataboxClient & {
    createAsset: ReturnType<typeof vi.fn>;
    createStoryAsset: ReturnType<typeof vi.fn>;
    copyAsset: ReturnType<typeof vi.fn>;
    deleteAsset: ReturnType<typeof vi.fn>;
    createCollection: ReturnType<typeof vi.fn>;
    createCollectionTreeBranch: ReturnType<typeof vi.fn>;
    createAttributeDefinition: ReturnType<typeof vi.fn>;
    createTag: ReturnType<typeof vi.fn>;
    getTags: ReturnType<typeof vi.fn>;
    createAttributePolicy: ReturnType<typeof vi.fn>;
    createRenditionPolicy: ReturnType<typeof vi.fn>;
    getRenditionPolicies: ReturnType<typeof vi.fn>;
    createRenditionDefinition: ReturnType<typeof vi.fn>;
    flushWorkspace: ReturnType<typeof vi.fn>;
    createWorkspace: ReturnType<typeof vi.fn>;
    getWorkspaceIdFromSlug: ReturnType<typeof vi.fn>;
    initWorkspace: ReturnType<typeof vi.fn>;
    authenticate: ReturnType<typeof vi.fn>;
};

/**
 * A DataboxClient that performs no I/O: every method is a spy. Used by the
 * tests that exercise callers of the client rather than the client itself
 * (the client has its own suite, on a mocked axios instance).
 */
export function createFakeDataboxClient(
    overrides: Partial<Record<keyof FakeDataboxClient, any>> = {}
): FakeDataboxClient {
    const client: Record<string, any> = {
        createAsset: vi.fn(async () => ({id: 'asset-1'})),
        createStoryAsset: vi.fn(async () => ({
            id: 'story-1',
            storyCollection: {id: 'story-coll-1'},
        })),
        copyAsset: vi.fn(async () => undefined),
        deleteAsset: vi.fn(async () => undefined),
        createCollection: vi.fn(async () => 'coll-1'),
        createCollectionTreeBranch: vi.fn(async () => 'coll-1'),
        createAttributeDefinition: vi.fn(async () => ({id: 'attr-def-1'})),
        createTag: vi.fn(async () => ({id: 'tag-1'})),
        getTags: vi.fn(async () => []),
        createAttributePolicy: vi.fn(async () => ({id: 'attr-policy-1'})),
        createRenditionPolicy: vi.fn(async () => 'rendition-policy-1'),
        getRenditionPolicies: vi.fn(async () => []),
        createRenditionDefinition: vi.fn(async () => 'rendition-def-1'),
        flushWorkspace: vi.fn(async () => 'workspace-1'),
        createWorkspace: vi.fn(async () => 'workspace-1'),
        getWorkspaceIdFromSlug: vi.fn(async () => 'workspace-1'),
        initWorkspace: vi.fn(async () => 'workspace-1'),
        authenticate: vi.fn(async () => undefined),
        ...overrides,
    };

    return client as FakeDataboxClient;
}

/**
 * Minimal express Response double: the asset servers only use these four.
 */
export function createFakeResponse() {
    const res: Record<string, any> = {
        statusCode: undefined as number | undefined,
        body: undefined as any,
        sentFile: undefined as string | undefined,
        redirectedTo: undefined as [number, string] | undefined,
    };

    res.status = vi.fn((code: number) => {
        res.statusCode = code;

        return res;
    });
    res.send = vi.fn((body: any) => {
        res.body = body;

        return res;
    });
    res.sendFile = vi.fn((file: string) => {
        res.sentFile = file;

        return res;
    });
    res.redirect = vi.fn((code: number, url: string) => {
        res.redirectedTo = [code, url];

        return res;
    });

    return res as typeof res & import('express').Response;
}
