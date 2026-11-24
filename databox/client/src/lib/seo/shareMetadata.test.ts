// @vitest-environment node
import {describe, expect, it, vi} from 'vitest';
import {
    loadPublicShare,
    sharePageMetadata,
    sharePreviewImageUrl,
    shareTitle,
    type PublicShare,
} from './shareMetadata';
import type {Asset} from '@/types/api';

function asset(
    name: string,
    thumbnail?: {url: string; type: string},
    preview?: {url: string; type: string}
): Asset {
    return {
        id: name,
        name,
        thumbnail: thumbnail ? {file: thumbnail} : null,
        preview: preview ? {file: preview} : null,
    } as unknown as Asset;
}

const one: PublicShare = {
    id: 's1',
    assets: [
        asset('Sunset.jpg', {url: 'https://s3/t.jpg?sig', type: 'image/jpeg'}),
    ],
};

const many: PublicShare = {
    id: 's1',
    assets: [
        asset('Clip.mp4', {url: 'https://s3/clip.svg', type: 'image/svg+xml'}),
        asset('Sunset.jpg', undefined, {
            url: 'https://s3/p.webp',
            type: 'image/webp',
        }),
        asset('Doc.pdf'),
    ],
};

function access(status: number, body: unknown) {
    const get = vi.fn().mockResolvedValue({
        status,
        body: typeof body === 'string' ? body : JSON.stringify(body),
    });

    return {get, access: {apiUrl: 'https://api-databox', verifySsl: true, get}};
}

describe('loadPublicShare', () => {
    it('reads the share anonymously', async () => {
        const {get, access: a} = access(200, one);
        await expect(loadPublicShare('s1', 'tok', a)).resolves.toEqual(one);
        expect(get).toHaveBeenCalledWith(
            'https://api-databox/shares/s1/public?token=tok',
            {
                headers: {Accept: 'application/json'},
                rejectUnauthorized: true,
                timeout: 3000,
            }
        );
    });

    it('gives up on what it cannot show', async () => {
        for (const {access: a} of [
            access(401, {}),
            access(404, {}),
            access(200, 'oops'),
            access(200, {id: 's1', assets: []}),
        ]) {
            await expect(loadPublicShare('s1', 'tok', a)).resolves.toBeNull();
        }
        await expect(
            loadPublicShare('s1', 'tok', {
                apiUrl: 'https://api-databox',
                verifySsl: true,
                get: vi.fn().mockRejectedValue(new Error('timeout')),
            })
        ).resolves.toBeNull();
    });
});

describe('share metadata', () => {
    it('names the asset, or the first one and how many more', () => {
        expect(shareTitle(one)).toBe('Sunset.jpg');
        expect(shareTitle(many)).toBe('Clip.mp4 (+2)');
    });

    it('picks the first image a preview can show', () => {
        expect(sharePreviewImageUrl(one)).toBe('https://s3/t.jpg?sig');
        expect(sharePreviewImageUrl(many)).toBe('https://s3/p.webp');
        expect(
            sharePreviewImageUrl({id: 's1', assets: [asset('Doc.pdf')]})
        ).toBeUndefined();
    });

    it('describes the share, never indexed', () => {
        expect(sharePageMetadata('s1', 'tok', one)).toMatchObject({
            title: 'Sunset.jpg',
            robots: {index: false, follow: false},
            openGraph: {
                url: '/s/s1/tok',
                images: [{url: '/api/og/share/s1/tok'}],
            },
            twitter: {card: 'summary_large_image'},
        });
        const metadata = sharePageMetadata('s1', 'tok', {
            id: 's1',
            assets: [asset('Doc.pdf')],
        });
        expect(metadata.twitter).toEqual({card: 'summary'});
        expect(sharePageMetadata('s1', 'tok', null)).toEqual({
            robots: {index: false, follow: false},
        });
    });
});
