import {createFakeDataboxClient} from '../../helpers/databox';
import {createTestLogger} from '../../helpers/logger';
import {Asset} from '../../../src/indexers';

const holder = vi.hoisted(() => ({
    client: undefined as any,
    indexed: [] as Asset[],
    runServerCalls: 0,
    logLevel: undefined as string | undefined,
}));

vi.mock('../../../src/databox/client.js', () => ({
    createDataboxClientFromConfig: () => holder.client,
}));

vi.mock('../../../src/server', () => ({
    runServer: () => {
        holder.runServerCalls++;

        return {close: vi.fn()};
    },
}));

// commandUtil.ts imports '../lib/logger' while index.ts imports
// '../lib/logger.js'; vitest resolves both to the same module.
vi.mock('../../../src/lib/logger.js', () => ({
    createLogger: () => createTestLogger(),
    setLogLevel: (level: string) => {
        holder.logLevel = level;
    },
}));

vi.mock('../../../src/indexers.js', () => ({
    indexers: {
        fs: async function* () {
            yield {
                workspaceId: 'ws-1',
                key: 'a/b.txt',
                path: 'a/b.txt',
            } as Asset;
        },
    },
}));

vi.mock('../../../src/databox/entrypoint.js', () => ({
    consume: async (
        _location: any,
        _client: any,
        iterator: AsyncGenerator<Asset>
    ) => {
        for await (const asset of iterator) {
            holder.indexed.push(asset);
        }
    },
}));

const {default: indexCommand} = await import('../../../src/command/index');

beforeEach(() => {
    holder.client = createFakeDataboxClient();
    holder.indexed = [];
    holder.runServerCalls = 0;
    holder.logLevel = undefined;
});

describe('indexCommand', () => {
    it('authenticates, indexes the location and starts the server by default', async () => {
        await indexCommand('fs_test', {debug: false});

        expect(holder.client.authenticate).toHaveBeenCalledTimes(1);
        expect(holder.indexed.map(a => a.key)).toEqual(['a/b.txt']);
        expect(holder.runServerCalls).toEqual(1);
    });

    it('skips the server when server is false (--no-server)', async () => {
        await indexCommand('fs_test', {debug: false, server: false});

        expect(holder.indexed.map(a => a.key)).toEqual(['a/b.txt']);
        expect(holder.runServerCalls).toEqual(0);
    });

    it('still starts the server when server is explicitly true', async () => {
        await indexCommand('fs_test', {debug: false, server: true});

        expect(holder.runServerCalls).toEqual(1);
    });

    it('raises the log level with --debug', async () => {
        await indexCommand('fs_test', {debug: true, server: false});

        expect(holder.logLevel).toEqual('debug');
    });

    it('fails on an unknown location before touching the API', async () => {
        await expect(
            indexCommand('nope', {debug: false, server: false})
        ).rejects.toThrow('Unknown location nope');

        expect(holder.runServerCalls).toEqual(0);
    });
});
