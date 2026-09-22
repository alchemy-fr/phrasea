import {mkdtemp, mkdir, writeFile, rm} from 'fs/promises';
import {tmpdir} from 'os';
import path from 'path';
import {fsIndexer} from '../../../../src/handlers/fs/indexer';
import {Asset} from '../../../../src/indexers';
import {IndexLocation} from '../../../../src/types/config';
import {FsConfig} from '../../../../src/handlers/fs/types';
import {
    createFakeDataboxClient,
    FakeDataboxClient,
} from '../../../helpers/databox';
import {createTestLogger} from '../../../helpers/logger';

let dir: string;
let client: FakeDataboxClient;

const location = (options: Partial<FsConfig> = {}) =>
    ({
        name: 'fs_test',
        type: 'fs',
        options: {
            dir,
            dirPrefix: 'e2e',
            workspaceSlug: 'test-workspace',
            ...options,
        },
    }) as IndexLocation<FsConfig>;

const run = async (loc: IndexLocation<FsConfig>): Promise<Asset[]> => {
    const out: Asset[] = [];
    for await (const asset of fsIndexer(
        loc,
        createTestLogger(),
        client,
        {} as any
    )) {
        out.push(asset);
    }

    return out.sort((a, b) => a.key.localeCompare(b.key));
};

beforeEach(async () => {
    dir = await mkdtemp(path.join(tmpdir(), 'indexer-fs-'));
    client = createFakeDataboxClient();
});

afterEach(async () => {
    await rm(dir, {recursive: true, force: true});
});

const touch = async (rel: string) => {
    const abs = path.join(dir, rel);
    await mkdir(path.dirname(abs), {recursive: true});
    await writeFile(abs, 'x');
};

describe('fsIndexer', () => {
    it('initialises the workspace from the location slug', async () => {
        await run(location());

        expect(client.initWorkspace).toHaveBeenCalledTimes(1);
        expect(client.initWorkspace.mock.calls[0][0]).toMatchObject({
            slug: 'test-workspace',
        });
    });

    it('yields one asset per file, keyed under the prefix', async () => {
        await touch('root.txt');
        await touch('level1/a.txt');
        await touch('level1/level2/b.txt');

        const assets = await run(location());

        expect(assets.map(a => a.key)).toEqual([
            'e2e/level1/a.txt',
            'e2e/level1/level2/b.txt',
            'e2e/root.txt',
        ]);
        expect(assets.every(a => a.workspaceId === 'workspace-1')).toBe(true);
        expect(assets.every(a => a.isPrivate === true)).toBe(true);
    });

    it('yields hidden files: the blacklist is applied later, in consume()', async () => {
        await touch('.hidden.txt');

        expect((await run(location())).map(a => a.key)).toEqual([
            'e2e/.hidden.txt',
        ]);
    });

    it('resolves sourcePath against sourceDir', async () => {
        await touch('a/b.txt');

        const [asset] = await run(location({sourceDir: '/host/pictures'}));

        expect(asset.sourcePath).toEqual('/host/pictures/a/b.txt');
    });

    it('yields nothing for an empty directory', async () => {
        expect(await run(location())).toEqual([]);
    });

    it('takes flushExisting from the JSON config', async () => {
        await run(location({createNewWorkspace: true} as any));
        expect(client.initWorkspace.mock.calls[0][0].flushExisting).toBe(true);
    });

    it('takes flushExisting from the --create-new-workspace flag', async () => {
        const out: Asset[] = [];
        for await (const asset of fsIndexer(
            location(),
            createTestLogger(),
            client,
            {createNewWorkspace: true} as any
        )) {
            out.push(asset);
        }

        expect(client.initWorkspace.mock.calls[0][0].flushExisting).toBe(true);
    });

    it('does not flush when neither the config nor the flag asks for it', async () => {
        await run(location());
        expect(client.initWorkspace.mock.calls[0][0].flushExisting).toBe(false);
    });
});
