import {mkdtemp, mkdir, writeFile, rm} from 'fs/promises';
import {tmpdir} from 'os';
import path from 'path';
import {fsAssetServerFactory} from '../../../../src/handlers/fs/server';
import {IndexLocation} from '../../../../src/types/config';
import {FsConfig} from '../../../../src/handlers/fs/types';
import {createFakeResponse} from '../../../helpers/databox';
import {createTestLogger, TestLogger} from '../../../helpers/logger';

let dir: string;
let logger: TestLogger;

const location = (options: Partial<FsConfig> = {}) =>
    ({
        name: 'fs_test',
        type: 'fs',
        options: {dir, dirPrefix: 'e2e', ...options},
    }) as IndexLocation<FsConfig>;

beforeEach(async () => {
    dir = await mkdtemp(path.join(tmpdir(), 'indexer-fs-server-'));
    logger = createTestLogger();
});

afterEach(async () => {
    await rm(dir, {recursive: true, force: true});
});

const touch = async (rel: string) => {
    const abs = path.join(dir, rel);
    await mkdir(path.dirname(abs), {recursive: true});
    await writeFile(abs, 'content');

    return abs;
};

describe('fsAssetServerFactory', () => {
    it('maps a prefixed path back onto the watch dir and serves the file', async () => {
        const abs = await touch('a/b.txt');
        const res = createFakeResponse();

        await fsAssetServerFactory(location(), logger)('e2e/a/b.txt', res, {});

        expect(res.sendFile).toHaveBeenCalledWith(abs);
    });

    it('uses the path as-is when no prefix is configured', async () => {
        const abs = await touch('a/b.txt');
        const res = createFakeResponse();

        await fsAssetServerFactory(location({dirPrefix: undefined}), logger)(
            abs,
            res,
            {}
        );

        expect(res.sendFile).toHaveBeenCalledWith(abs);
    });

    it('answers 404 for a path that is not on disk', async () => {
        const res = createFakeResponse();

        await fsAssetServerFactory(location(), logger)(
            'e2e/missing.txt',
            res,
            {}
        );

        expect(res.status).toHaveBeenCalledWith(404);
        expect(res.body).toEqual({
            error: 'Not Found',
            error_description: `"${path.join(dir, '/missing.txt')}" not found`,
        });
        expect(res.sendFile).not.toHaveBeenCalled();
        expect(logger.hasMessageMatching('HTTP Not found')).toBe(true);
    });

    it('resolves the watch dir once, at factory time', async () => {
        const abs = await touch('a.txt');
        const handler = fsAssetServerFactory(location(), logger);
        const res = createFakeResponse();

        await handler('e2e/a.txt', res, {});

        expect(res.sendFile).toHaveBeenCalledWith(abs);
    });
});
