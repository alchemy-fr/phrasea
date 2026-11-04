import {getAlternateUrls} from '../../src/alternateUrl';
import {Asset} from '../../src/indexers';
import {IndexLocation} from '../../src/types/config';
import {getLocation} from '../../src/locations';

const asset = {
    workspaceId: 'ws-1',
    key: 'fs/a/b.jpg',
    path: 'fs/a/b.jpg',
    sourcePath: '/source/a/b.jpg',
} as Asset;

describe('getAlternateUrls', () => {
    it('uses the alternateUrls declared on the location', () => {
        expect(getAlternateUrls(asset, getLocation('fs_test'))).toEqual([
            {type: 'indexer', url: 'indexer:///source/a/b.jpg'},
        ]);
    });

    it('falls back to the global config when the location declares none', () => {
        expect(getAlternateUrls(asset, getLocation('s3_test'))).toEqual([
            {type: 'global', url: 'global://fs/a/b.jpg'},
        ]);
    });

    it('substitutes ${path} and ${sourcePath}', () => {
        const location = {
            name: 'x',
            type: 'fs',
            options: {},
            alternateUrls: [
                {name: 'by-path', pathPattern: 'scheme://${path}'},
                {name: 'by-source', pathPattern: 'scheme://${sourcePath}'},
            ],
        } as IndexLocation<any>;

        expect(getAlternateUrls(asset, location)).toEqual([
            {type: 'by-path', url: 'scheme://fs/a/b.jpg'},
            {type: 'by-source', url: 'scheme:///source/a/b.jpg'},
        ]);
    });

    it('yields an empty list when the location declares an empty array', () => {
        const location = {
            name: 'x',
            type: 'fs',
            options: {},
            alternateUrls: [],
        } as unknown as IndexLocation<any>;

        expect(getAlternateUrls(asset, location)).toEqual([]);
    });

    it('substitutes each placeholder of a two-placeholder pattern', () => {
        // The regex used to be greedy, so `${path}-${sourcePath}` captured
        // `path}-${sourcePath` — not a key of the substitution dictionary.
        const location = {
            name: 'x',
            type: 'fs',
            options: {},
            alternateUrls: [
                {name: 'both', pathPattern: '${path}-${sourcePath}'},
            ],
        } as IndexLocation<any>;

        expect(getAlternateUrls(asset, location)).toEqual([
            {type: 'both', url: `${asset.path}-${asset.sourcePath}`},
        ]);
    });
});

describe('getAlternateUrls without any configuration', () => {
    beforeEach(() => {
        vi.resetModules();
    });

    afterEach(() => {
        vi.doUnmock('../../src/configLoader');
    });

    it('returns undefined', async () => {
        vi.doMock('../../src/configLoader', () => ({
            config: {alternateUrls: undefined},
            getConfig: () => undefined,
        }));

        const {getAlternateUrls: fresh} =
            await import('../../src/alternateUrl');

        const location = {
            name: 'x',
            type: 'fs',
            options: {},
        } as IndexLocation<any>;

        expect(fresh(asset, location)).toBeUndefined();
    });
});
