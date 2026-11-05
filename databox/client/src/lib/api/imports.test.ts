import {beforeEach, describe, expect, it, vi} from 'vitest';

const get = vi.fn();
const post = vi.fn();
const del = vi.fn();
const upload = vi.fn();

vi.mock('./http', () => ({
    api: {
        get: (...args: unknown[]) => get(...args),
        post: (...args: unknown[]) => post(...args),
        delete: (...args: unknown[]) => del(...args),
    },
}));
vi.mock('./upload', () => ({
    multipartUpload: (...args: unknown[]) => upload(...args),
}));

import {
    ASSET_IMPORTS_PAGE_SIZE,
    createAssetImport,
    deleteAssetImport,
    getAssetImport,
    getAssetImportErrors,
    getAssetImports,
} from './imports';

const multipart = {uploadId: 'up-1', parts: [{ETag: '"a"', PartNumber: 1}]};

describe('createAssetImport', () => {
    beforeEach(() => {
        post.mockReset();
        upload.mockReset();
        upload.mockResolvedValue(multipart);
        post.mockResolvedValue({id: 'imp-1'});
    });

    it('uploads the file then registers the import', async () => {
        const file = new File(['{}'], 'assets.ndjson');
        const onProgress = vi.fn();

        const result = await createAssetImport(file, {
            workspaceId: 'ws-1',
            collectionId: 'col-1',
            format: 'ndjson',
            privacy: 2,
            onProgress,
        });

        expect(result).toEqual({id: 'imp-1'});
        expect(upload).toHaveBeenCalledWith(file, {
            onProgress,
            signal: undefined,
        });
        expect(post).toHaveBeenCalledTimes(1);
        const [url, body] = post.mock.calls[0];
        expect(url).toBe('/asset-imports');
        expect(body).toEqual({
            workspace: '/workspaces/ws-1',
            collection: '/collections/col-1',
            format: 'ndjson',
            privacy: 2,
            originalName: 'assets.ndjson',
            multipart,
        });
        expect(body.data).toBeUndefined();
    });

    it('sends no collection when the destination is the workspace', async () => {
        await createAssetImport(new File(['[]'], 'a.json'), {
            workspaceId: 'ws-1',
            format: 'json',
        });

        const body = post.mock.calls[0][1];
        expect(body.workspace).toBe('/workspaces/ws-1');
        expect(body.collection).toBeUndefined();
        expect(body.privacy).toBeUndefined();
    });

    it('does not register the import when the upload fails', async () => {
        upload.mockRejectedValue(new Error('boom'));

        await expect(
            createAssetImport(new File(['[]'], 'a.json'), {
                workspaceId: 'ws-1',
                format: 'json',
            })
        ).rejects.toThrow('boom');
        expect(post).not.toHaveBeenCalled();
    });
});

describe('asset import reads', () => {
    beforeEach(() => {
        get.mockReset();
        del.mockReset();
        get.mockResolvedValue({totalItems: 1, member: [{id: 'x'}]});
    });

    it('lists the imports of a workspace', async () => {
        const page = await getAssetImports('ws-1', 2);

        expect(get).toHaveBeenCalledWith('/asset-imports', {
            params: {
                workspace: 'ws-1',
                page: 2,
                limit: ASSET_IMPORTS_PAGE_SIZE,
            },
        });
        expect(page.total).toBe(1);
        expect(page.items).toEqual([{id: 'x'}]);
    });

    it('lists the errors of an import', async () => {
        const page = await getAssetImportErrors('imp-1');

        expect(get.mock.calls[0][0]).toBe('/asset-imports/imp-1/errors');
        expect(get.mock.calls[0][1].params.page).toBe(1);
        expect(page.items).toEqual([{id: 'x'}]);
    });

    it('gets and deletes an import', async () => {
        get.mockResolvedValue({id: 'imp-1'});

        expect(await getAssetImport('imp-1')).toEqual({id: 'imp-1'});
        expect(get).toHaveBeenCalledWith('/asset-imports/imp-1');

        await deleteAssetImport('imp-1');
        expect(del).toHaveBeenCalledWith('/asset-imports/imp-1');
    });
});
