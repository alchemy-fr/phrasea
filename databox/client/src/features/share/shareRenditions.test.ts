import {describe, expect, it} from 'vitest';
import type {Asset, Share} from '@/types/api';
import {
    extensionFromMimeType,
    getDefaultViewRendition,
    getDownloadFileName,
    getRenditionFile,
    getShareRenditions,
} from './shareRenditions';

const labels = {preview: 'Preview', main: 'Main', source: 'Source'};

function asset(id: string, extra: Partial<Asset> = {}): Asset {
    return {id, name: `Asset ${id}`, ...extra} as unknown as Asset;
}

function share(assets: Asset[], extra: Partial<Share> = {}): Share {
    return {id: 's1', assets, alternateUrls: [], ...extra} as unknown as Share;
}

describe('getShareRenditions', () => {
    it('keeps renditions sharing the same file apart, by rendition id', () => {
        const a = asset('a1');
        const list = getShareRenditions(
            share([a, asset('a2')], {
                alternateUrls: [
                    {
                        id: 'r1',
                        name: 'preview',
                        displayName: 'Aperçu',
                        url: 'https://api/s/1/r/d1',
                        type: 'image/jpeg',
                        size: 10,
                        assetId: 'a1',
                    },
                    {
                        id: 'r2',
                        name: 'original',
                        url: 'https://api/s/1/r/d2',
                        type: 'image/jpeg',
                        size: 10,
                        assetId: 'a1',
                    },
                    {
                        id: 'r3',
                        name: 'preview',
                        url: 'https://api/s/1/r/d1?asset=a2',
                        type: 'image/jpeg',
                        assetId: 'a2',
                    },
                ],
            }),
            a,
            labels
        );

        expect(list.map(r => r.id)).toEqual(['r1', 'r2']);
        expect(list.map(r => r.label)).toEqual(['Aperçu', 'original']);
    });

    it('gives the URLs without asset to the only asset of the share', () => {
        const a = asset('a1');
        const list = getShareRenditions(
            share([a], {
                alternateUrls: [
                    {name: 'preview', url: 'u', definitionId: 'd1'},
                ],
            }),
            a,
            labels
        );

        expect(list).toHaveLength(1);
        expect(list[0].id).toBe('d1');
    });

    it('falls back to the renditions embedded in the asset', () => {
        const file = {url: 'https://s3/f.jpg', type: 'image/jpeg', size: 3};
        const a = asset('a1', {
            preview: {name: 'preview', file},
            main: {name: 'main', file},
            source: {url: 'https://s3/src.tif', type: 'image/tiff'},
        } as Partial<Asset>);

        const list = getShareRenditions(share([a]), a, labels);

        // Same file for the preview and the main rendition: both listed
        expect(list.map(r => [r.id, r.label])).toEqual([
            ['a1:preview', 'Preview'],
            ['a1:main', 'Main'],
            ['a1:source', 'Source'],
        ]);
    });
});

describe('getDefaultViewRendition / getRenditionFile', () => {
    const renditions = [
        {
            id: 'r1',
            name: 'original',
            label: 'Original',
            url: 'u1',
            type: 'image/tiff',
        },
        {id: 'r2', name: 'web', label: 'Web', url: 'u2', type: 'image/jpeg'},
    ];

    it("displays the asset's preview rendition, played from the embedded file", () => {
        const a = asset('a1', {
            preview: {
                name: 'web',
                file: {url: 'https://s3/web.jpg', type: 'image/jpeg'},
            },
        } as Partial<Asset>);
        const r = getDefaultViewRendition(a, renditions);

        expect(r?.id).toBe('r2');
        expect(getRenditionFile(a, r!).url).toBe('https://s3/web.jpg');
    });

    it('plays the share URL of a rendition not embedded in the asset', () => {
        const file = getRenditionFile(asset('a1'), renditions[0]);

        expect(file.url).toBe('u1');
        expect(file.id).toBe('r1');
        expect(file.type).toBe('image/tiff');
    });
});

describe('download file names', () => {
    it.each([
        ['image/jpeg', 'jpg'],
        ['image/png', 'png'],
        ['video/x-matroska', 'mkv'],
        ['application/vnd.oasis.opendocument.text', undefined],
        [undefined, undefined],
    ])('%s → %s', (type, ext) => {
        expect(extensionFromMimeType(type)).toBe(ext);
    });

    it('names the file after the asset and the rendition', () => {
        expect(
            getDownloadFileName('Beach.JPG', {
                id: 'r',
                name: 'web',
                label: 'Web',
                url: 'u',
                type: 'image/jpeg',
            })
        ).toBe('Beach - Web.jpg');
        expect(
            getDownloadFileName(undefined, {
                id: 'r',
                name: 'x',
                label: 'Doc',
                url: 'u',
            })
        ).toBe('asset - Doc');
    });
});
