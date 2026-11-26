// @vitest-environment node
import {describe, expect, it} from 'vitest';
import {isIndexingAllowed} from './indexing';
import {appMetadata, OG_IMAGE, pageMetadata, plainText} from './metadata';
import {appManifest} from './manifest';
import {appRobots, PREVIEW_ROBOTS} from './robots';

describe('isIndexingAllowed', () => {
    it('reads ALLOW_INDEXING, else the default', () => {
        expect(isIndexingAllowed({ALLOW_INDEXING: 'true'}, false)).toBe(true);
        expect(isIndexingAllowed({ALLOW_INDEXING: '1'}, false)).toBe(true);
        expect(isIndexingAllowed({ALLOW_INDEXING: 'false'}, true)).toBe(false);
        expect(isIndexingAllowed({ALLOW_INDEXING: 'No'}, true)).toBe(false);
        expect(isIndexingAllowed({}, true)).toBe(true);
        expect(isIndexingAllowed({ALLOW_INDEXING: ''}, false)).toBe(false);
        expect(isIndexingAllowed({ALLOW_INDEXING: 'maybe'}, true)).toBe(true);
    });
});

describe('appMetadata', () => {
    const base = {name: 'Expose', description: 'Phrasea Expose'};

    it('describes the app and its brand preview', () => {
        const metadata = appMetadata({
            ...base,
            clientUrl: 'https://expose.phrasea.local',
            allowIndexing: true,
        });
        expect(metadata.metadataBase?.toString()).toBe(
            'https://expose.phrasea.local/'
        );
        expect(metadata.title).toEqual({
            default: 'Expose',
            template: '%s · Expose',
        });
        expect(metadata.robots).toEqual({index: true, follow: true});
        expect(metadata.openGraph).toMatchObject({
            siteName: 'Expose',
            title: 'Expose',
            description: 'Phrasea Expose',
            images: [{...OG_IMAGE, alt: 'Expose'}],
        });
        expect(metadata.twitter).toEqual({card: 'summary_large_image'});
        expect(metadata.manifest).toBeUndefined();
    });

    it('keeps the robots out when indexing is not allowed', () => {
        expect(
            appMetadata({...base, clientUrl: '', allowIndexing: false}).robots
        ).toEqual({index: false, follow: false});
    });

    it('omits the base URL when the client URL is not valid', () => {
        for (const clientUrl of [undefined, '', 'not an url']) {
            expect(
                appMetadata({...base, clientUrl, allowIndexing: false})
            ).not.toHaveProperty('metadataBase');
        }
    });
});

describe('pageMetadata', () => {
    it('describes a resource with its image', () => {
        expect(
            pageMetadata({
                title: 'Summer',
                description: 'Photos',
                image: '/api/og/publication/summer',
                url: '/summer',
                siteName: 'Expose',
            })
        ).toEqual({
            title: 'Summer',
            description: 'Photos',
            openGraph: {
                type: 'website',
                siteName: 'Expose',
                title: 'Summer',
                description: 'Photos',
                url: '/summer',
                images: [{url: '/api/og/publication/summer', alt: 'Summer'}],
            },
            twitter: {card: 'summary_large_image'},
            alternates: {canonical: '/summer'},
        });
    });

    it('falls back to the brand image', () => {
        const metadata = pageMetadata({
            title: 'Summer',
            url: '/summer',
            siteName: 'Expose',
        });
        expect(metadata).not.toHaveProperty('description');
        expect(metadata.openGraph).toMatchObject({
            images: [{...OG_IMAGE, alt: 'Expose'}],
        });
        expect(metadata.twitter).toEqual({card: 'summary'});
    });
});

describe('plainText', () => {
    it('strips the markup and decodes the entities', () => {
        expect(
            plainText(
                '<p>Hello&nbsp;<b>world</b> &amp; co &#8211; &#x41;</p>\n<script>alert(1)</script><p>Bye</p>'
            )
        ).toBe('Hello world & co – A Bye');
        expect(plainText('&unknown; &lt;b&gt;')).toBe('&unknown; <b>');
    });

    it('cuts after a whole word', () => {
        expect(plainText('The quick brown fox jumps', 15)).toBe('The quick…');
        expect(plainText('Short', 15)).toBe('Short');
        expect(plainText('Supercalifragilistic', 10)).toBe('Supercali…');
        expect(plainText('a'.repeat(300))).toHaveLength(200);
    });
});

describe('appManifest', () => {
    it('lists the icons, a maskable one included', () => {
        const manifest = appManifest({
            name: 'Phrasea Expose',
            shortName: 'Expose',
            description: 'Asset publications',
        });
        expect(manifest).toMatchObject({
            id: '/',
            name: 'Phrasea Expose',
            short_name: 'Expose',
            start_url: '/',
            display: 'standalone',
        });
        expect(manifest.icons?.map(i => i.src)).toEqual([
            '/favicon.svg',
            '/icons/icon-192.png',
            '/icons/icon-512.png',
            '/icons/icon-maskable-512.png',
        ]);
        expect(manifest.icons?.[3].purpose).toBe('maskable');
    });
});

describe('appRobots', () => {
    it('opens the app but its technical paths', () => {
        expect(appRobots({allowIndexing: true, disallow: ['/embed/']})).toEqual(
            {
                rules: {
                    userAgent: '*',
                    allow: ['/', '/api/og/'],
                    disallow: ['/auth/', '/api/', '/embed/'],
                },
            }
        );
    });

    it('keeps only the shares and their previews open otherwise', () => {
        expect(appRobots({allowIndexing: false})).toEqual({
            rules: {
                userAgent: '*',
                allow: ['/s/', '/api/og/'],
                disallow: '/',
            },
        });
    });

    it('opens the pages to the link preview robots on demand', () => {
        expect(
            appRobots({
                allowIndexing: false,
                allowPreviews: true,
                disallow: ['/embed/'],
            })
        ).toEqual({
            rules: [
                {
                    userAgent: '*',
                    allow: ['/s/', '/api/og/'],
                    disallow: '/',
                },
                {
                    userAgent: PREVIEW_ROBOTS,
                    allow: ['/', '/api/og/'],
                    disallow: ['/auth/', '/api/', '/embed/'],
                },
            ],
        });
        expect(PREVIEW_ROBOTS).toContain('Twitterbot');
        expect(PREVIEW_ROBOTS).toContain('LinkedInBot');
    });

    it('ignores allowPreviews when the app is indexed', () => {
        expect(appRobots({allowIndexing: true, allowPreviews: true})).toEqual(
            appRobots({allowIndexing: true})
        );
    });
});
