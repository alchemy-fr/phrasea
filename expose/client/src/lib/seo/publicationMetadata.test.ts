// @vitest-environment node
import {describe, expect, it, vi} from 'vitest';
import {NO_INDEX} from '@alchemy/core/seo/metadata';
import {
    assetPageMetadata as assetPageMetadataOf,
    loadPublicPublication,
    loadShareResolution,
    METADATA_REVALIDATE_S,
    previewImageUrl,
    publicationMetadataLoader,
    publicationPageMetadata as publicationPageMetadataOf,
    toPublicationMetadata,
} from './publicationMetadata';

const cached = vi.hoisted(() => ({options: [] as unknown[]}));

/** Kept in memory per arguments, as the data cache of Next does */
vi.mock('next/cache', () => ({
    unstable_cache: <A extends unknown[], R>(
        fn: (...args: A) => Promise<R>,
        _keyParts: string[],
        options: unknown
    ) => {
        cached.options.push(options);
        const entries = new Map<string, Promise<R>>();

        return (...args: A) => {
            const k = JSON.stringify(args);
            if (!entries.has(k)) {
                entries.set(k, fn(...args));
            }

            return entries.get(k)!;
        };
    },
}));
import type {Asset, Publication} from '@/types/api';

const cover: Asset = {
    id: 'a1',
    slug: 'sunset',
    title: 'Sunset',
    description: '<p>Over the <b>sea</b></p>',
    mimeType: 'image/jpeg',
    thumbUrl: 'https://s3/thumb.jpg?sig',
    thumbType: 'image/jpeg',
};

const publication: Publication = {
    id: 'p1',
    slug: 'summer',
    title: 'Summer',
    description: '<p>Photos &amp; videos</p>',
    authorized: true,
    publiclyListed: true,
    cover,
    assets: [
        cover,
        {
            id: 'a2',
            title: null,
            originalName: 'clip.mp4',
            mimeType: 'video/mp4',
            thumbUrl: 'https://s3/clip.svg',
            thumbType: 'image/svg+xml',
        },
    ],
};

function access(status: number, body: unknown) {
    const get = vi.fn().mockResolvedValue({
        status,
        body: typeof body === 'string' ? body : JSON.stringify(body),
    });

    return {
        get,
        access: {apiUrl: 'https://api-expose/', verifySsl: false, get},
    };
}

describe('loadPublicPublication', () => {
    it('reads the publication anonymously', async () => {
        const {get, access: a} = access(200, publication);
        await expect(
            loadPublicPublication('summer', undefined, a)
        ).resolves.toEqual(publication);
        expect(get).toHaveBeenCalledWith(
            'https://api-expose/publications/summer',
            {
                headers: {Accept: 'application/json'},
                rejectUnauthorized: false,
                timeout: 3000,
            }
        );
    });

    it('passes a valid share token only', async () => {
        const {get, access: a} = access(200, publication);
        await loadPublicPublication('p1', 'abc123', a);
        expect(get.mock.calls[0][1].headers).toEqual({
            'Accept': 'application/json',
            'X-Share-Tokens': 'abc123',
        });
        await loadPublicPublication('p1', 'abc\r\nX-Evil: 1', a);
        expect(get.mock.calls[1][1].headers).toEqual({
            Accept: 'application/json',
        });
    });

    it('keeps a protected publication publicly listed', async () => {
        const listed = {
            id: 'p1',
            title: 'Summer',
            description: 'Listed',
            authorized: false,
            cover: {
                id: 'a1',
                thumbUrl: 'https://s3/t.jpg',
                thumbType: 'image/jpeg',
            },
        };
        const {access: a} = access(200, listed);
        await expect(
            loadPublicPublication('p1', undefined, a)
        ).resolves.toEqual(listed);
    });

    it('gives up on what it cannot show', async () => {
        for (const {access: a} of [
            access(200, {id: 'p1', authorized: false}),
            access(401, {}),
            access(404, {}),
            access(200, 'not json'),
            access(200, 'null'),
        ]) {
            await expect(
                loadPublicPublication('p1', undefined, a)
            ).resolves.toBeNull();
        }
        const get = vi.fn().mockRejectedValue(new Error('timeout'));
        await expect(
            loadPublicPublication('p1', undefined, {
                apiUrl: 'https://api-expose',
                verifySsl: true,
                get,
            })
        ).resolves.toBeNull();
    });
});

describe('loadShareResolution', () => {
    it('resolves a valid token only', async () => {
        const resolution = {publication: {id: 'p1', slug: null, title: 'S'}};
        const {get, access: a} = access(200, resolution);
        await expect(loadShareResolution('tok1', a)).resolves.toEqual(
            resolution
        );
        expect(get.mock.calls[0][0]).toBe(
            'https://api-expose/publication-shares/resolve/tok1'
        );
        await expect(loadShareResolution('../x', a)).resolves.toBeNull();
        expect(get).toHaveBeenCalledTimes(1);
        await expect(
            loadShareResolution('tok1', access(404, {}).access)
        ).resolves.toBeNull();
    });
});

function publicationPageMetadata(
    p: Publication,
    options?: Parameters<typeof publicationPageMetadataOf>[1]
) {
    return publicationPageMetadataOf(toPublicationMetadata(p), options);
}

function assetPageMetadata(p: Publication, assetKey: string) {
    return assetPageMetadataOf(toPublicationMetadata(p), assetKey);
}

describe('publicationMetadataLoader', () => {
    it('reads the publication once for successive pages', async () => {
        const {get, access: a} = access(200, publication);
        const load = publicationMetadataLoader(() => a);
        expect(cached.options.at(-1)).toEqual({
            revalidate: METADATA_REVALIDATE_S,
        });

        const first = await load('summer');
        const second = await load('summer');
        expect(get).toHaveBeenCalledTimes(1);
        expect(assetPageMetadataOf(first!, 'a1')).toMatchObject({
            title: 'Sunset · Summer',
            openGraph: {
                images: [{url: '/api/og/publication/summer/asset/a1'}],
            },
        });
        expect(assetPageMetadataOf(second!, 'a2')).toMatchObject({
            title: 'clip.mp4 · Summer',
            openGraph: {images: [{url: '/api/og/publication/summer'}]},
        });

        await load('other');
        expect(get).toHaveBeenCalledTimes(2);
    });

    it('keeps no signed URL', async () => {
        const {access: a} = access(200, publication);
        const metadata = await publicationMetadataLoader(() => a)('summer');
        expect(JSON.stringify(metadata)).not.toContain('https://s3');
        expect(metadata).toEqual({
            id: 'p1',
            slug: 'summer',
            title: 'Summer',
            description: '<p>Photos &amp; videos</p>',
            publiclyListed: true,
            cover: {
                id: 'a1',
                slug: 'sunset',
                title: 'Sunset',
                description: '<p>Over the <b>sea</b></p>',
                hasPreviewImage: true,
            },
            assets: [
                expect.objectContaining({id: 'a1', hasPreviewImage: true}),
                expect.objectContaining({
                    id: 'a2',
                    originalName: 'clip.mp4',
                    hasPreviewImage: false,
                }),
            ],
        });
    });

    it('gives null when the publication cannot be read', async () => {
        const {access: a} = access(404, {});
        await expect(
            publicationMetadataLoader(() => a)('summer')
        ).resolves.toBeNull();
    });
});

describe('robots', () => {
    it('indexes a publicly listed publication', () => {
        expect(publicationPageMetadata(publication).robots).toBeUndefined();
        expect(assetPageMetadata(publication, 'a1').robots).toBeUndefined();
    });

    it('keeps an unlisted publication out of the search engines', () => {
        for (const publiclyListed of [false, undefined]) {
            const unlisted = {...publication, publiclyListed};
            expect(publicationPageMetadata(unlisted).robots).toEqual(NO_INDEX);
            expect(assetPageMetadata(unlisted, 'a1').robots).toEqual(NO_INDEX);
            expect(assetPageMetadata(unlisted, 'unknown').robots).toEqual(
                NO_INDEX
            );
        }
    });
});

describe('metadata', () => {
    it('describes a publication with its cover', () => {
        expect(publicationPageMetadata(publication)).toMatchObject({
            title: 'Summer',
            description: 'Photos & videos',
            openGraph: {
                url: '/summer',
                images: [{url: '/api/og/publication/summer'}],
            },
            twitter: {card: 'summary_large_image'},
        });
        expect(
            publicationPageMetadata(publication, {
                url: '/s/tok1',
                shareToken: 'tok1',
            }).openGraph
        ).toMatchObject({
            url: '/s/tok1',
            images: [{url: '/api/og/publication/summer?share=tok1'}],
        });
    });

    it('describes an asset, by id or slug', () => {
        const expected = {
            title: 'Sunset · Summer',
            description: 'Over the sea',
            openGraph: {
                url: '/summer/a1',
                images: [{url: '/api/og/publication/summer/asset/a1'}],
            },
        };
        expect(assetPageMetadata(publication, 'a1')).toMatchObject(expected);
        expect(assetPageMetadata(publication, 'sunset')).toMatchObject(
            expected
        );
    });

    it('falls back on the publication', () => {
        expect(assetPageMetadata(publication, 'a2')).toMatchObject({
            title: 'clip.mp4 · Summer',
            description: 'Photos & videos',
            openGraph: {images: [{url: '/api/og/publication/summer'}]},
        });
        expect(assetPageMetadata(publication, 'unknown')).toMatchObject({
            title: 'Summer',
        });
        expect(assetPageMetadata(publication, '%E0%A4%A')).toMatchObject({
            title: 'Summer',
        });
    });

    it('keeps the images a preview can show', () => {
        expect(previewImageUrl(cover)).toBe('https://s3/thumb.jpg?sig');
        expect(
            previewImageUrl({
                ...cover,
                thumbType: 'video/mp4',
                previewUrl: 'https://s3/p.png',
                previewType: 'image/png',
            })
        ).toBe('https://s3/p.png');
        expect(previewImageUrl(publication.assets![1])).toBeUndefined();
        expect(previewImageUrl(null)).toBeUndefined();
    });
});
