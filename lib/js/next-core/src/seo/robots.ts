import type {MetadataRoute} from 'next';

/** The images of the link previews (see server/imageProxy) */
const PREVIEW_IMAGES = '/api/og/';

/**
 * The robots.txt of a client. When it must not be indexed, the share pages
 * and the images of their previews stay open to the crawlers: the link
 * preview robots (Twitterbot…) follow robots.txt, and the search engines are
 * kept out of those pages by their `noindex` robots meta tag instead.
 */
export function appRobots({
    allowIndexing,
    disallow = [],
}: {
    allowIndexing: boolean;
    disallow?: string[];
}): MetadataRoute.Robots {
    if (allowIndexing) {
        return {
            rules: {
                userAgent: '*',
                allow: ['/', PREVIEW_IMAGES],
                disallow: ['/auth/', '/api/', ...disallow],
            },
        };
    }

    return {
        rules: {
            userAgent: '*',
            allow: ['/s/', PREVIEW_IMAGES],
            disallow: '/',
        },
    };
}
