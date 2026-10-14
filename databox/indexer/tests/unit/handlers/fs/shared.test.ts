import {mkdtemp, mkdir, writeFile, rm} from 'fs/promises';
import {tmpdir} from 'os';
import path from 'path';
import {
    createAsset,
    getDirConfig,
    getFiles,
} from '../../../../src/handlers/fs/shared';

describe('createAsset', () => {
    it('derives key and path from the watch dir when no prefix is set', () => {
        expect(
            createAsset('ws-1', '/fs-watch/a/b.jpg', 'fs_test', '/fs-watch')
        ).toEqual({
            workspaceId: 'ws-1',
            key: '/fs-watch/a/b.jpg',
            path: '/fs-watch/a/b.jpg',
            publicUrl:
                'http://indexer.test/assets/?path=%2Ffs-watch%2Fa%2Fb.jpg&source=fs_test',
            isPrivate: true,
            sourcePath: '/fs-watch/a/b.jpg',
        });
    });

    it('replaces the watch dir with dirPrefix', () => {
        const asset = createAsset(
            'ws-1',
            '/fs-watch/a/b.jpg',
            'fs_test',
            '/fs-watch',
            'fs'
        );

        expect(asset.key).toEqual('fs/a/b.jpg');
        expect(asset.path).toEqual('fs/a/b.jpg');
        expect(asset.publicUrl).toEqual(
            'http://indexer.test/assets/?path=fs%2Fa%2Fb.jpg&source=fs_test'
        );
    });

    it('rewrites sourcePath against sourceDir', () => {
        const asset = createAsset(
            'ws-1',
            '/fs-watch/a/b.jpg',
            'fs_test',
            '/fs-watch',
            'fs',
            '/host/pictures'
        );

        expect(asset.sourcePath).toEqual('/host/pictures/a/b.jpg');
        expect(asset.key).toEqual('fs/a/b.jpg');
    });

    it('marks every asset as private', () => {
        expect(
            createAsset('ws-1', '/fs-watch/b.jpg', 'fs_test', '/fs-watch')
                .isPrivate
        ).toBe(true);
    });

    it('preserves spaces and accents in the derived paths', () => {
        const asset = createAsset(
            'ws-1',
            '/fs-watch/Dossier Accentué/Été 2024.txt',
            'fs_test',
            '/fs-watch',
            'e2e',
            '/source'
        );

        expect(asset.key).toEqual('e2e/Dossier Accentué/Été 2024.txt');
        expect(asset.sourcePath).toEqual(
            '/source/Dossier Accentué/Été 2024.txt'
        );
        expect(asset.publicUrl).toEqual(
            'http://indexer.test/assets/?path=e2e%2FDossier+Accentu%C3%A9%2F%C3%89t%C3%A9+2024.txt&source=fs_test'
        );
    });

    it('handles a file sitting directly in the watch dir', () => {
        const asset = createAsset(
            'ws-1',
            '/fs-watch/root.txt',
            'fs_test',
            '/fs-watch',
            'e2e'
        );

        expect(asset.key).toEqual('e2e/root.txt');
    });
});

describe('getFiles', () => {
    let dir: string;

    beforeEach(async () => {
        dir = await mkdtemp(path.join(tmpdir(), 'indexer-getfiles-'));
    });

    afterEach(async () => {
        await rm(dir, {recursive: true, force: true});
    });

    const touch = async (rel: string) => {
        const abs = path.join(dir, rel);
        await mkdir(path.dirname(abs), {recursive: true});
        await writeFile(abs, 'x');

        return abs;
    };

    const collect = async () => {
        const out: string[] = [];
        for await (const f of getFiles(dir)) {
            out.push(path.relative(dir, f));
        }

        return out.sort();
    };

    it('walks nested directories', async () => {
        await touch('root.txt');
        await touch('level1/a.txt');
        await touch('level1/level2/b.txt');

        expect(await collect()).toEqual([
            'level1/a.txt',
            'level1/level2/b.txt',
            'root.txt',
        ]);
    });

    it('yields hidden files too: filtering happens downstream', async () => {
        await touch('.hidden.txt');
        await touch('.hidden-dir/inside.txt');

        expect(await collect()).toEqual([
            '.hidden-dir/inside.txt',
            '.hidden.txt',
        ]);
    });

    it('yields absolute paths', async () => {
        const abs = await touch('a.txt');

        const out: string[] = [];
        for await (const f of getFiles(dir)) {
            out.push(f);
        }

        expect(out).toEqual([abs]);
    });

    it('yields nothing for an empty tree', async () => {
        await mkdir(path.join(dir, 'empty'), {recursive: true});

        expect(await collect()).toEqual([]);
    });
});

describe('getDirConfig', () => {
    it('reads dir, dirPrefix and sourceDir from the location options', () => {
        expect(
            getDirConfig({
                dir: '/fs-watch',
                dirPrefix: 'fs',
                sourceDir: '/source',
            } as any)
        ).toEqual({
            watchDir: '/fs-watch',
            dirPrefix: 'fs',
            sourceDir: '/source',
        });
    });

    it('leaves the optional keys undefined', () => {
        expect(getDirConfig({dir: '/fs-watch'} as any)).toEqual({
            watchDir: '/fs-watch',
            dirPrefix: undefined,
            sourceDir: undefined,
        });
    });

    it('exits the process when dir is missing', () => {
        // getStrict() logs and calls process.exit(1) rather than throwing.
        // Vitest turns that call into an error, which is what surfaces here.
        const error = vi
            .spyOn(console, 'error')
            .mockImplementation(() => undefined);

        expect(() => getDirConfig({} as any)).toThrow(/process\.exit/);
        expect(error).toHaveBeenCalledWith('Missing config "dir"');

        error.mockRestore();
    });
});
