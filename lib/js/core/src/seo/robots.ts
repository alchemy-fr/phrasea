import type {MetadataRoute} from 'next';

/** The images of the link previews (see server/imageProxy) */
const PREVIEW_IMAGES = '/api/og/';

/**
 * The robots fetching a page to show its link preview, which follow
 * robots.txt but never put the page in a search engine.
 */
export const PREVIEW_ROBOTS = [
    'Twitterbot',
    'facebookexternalhit',
    'Facebot',
    'LinkedInBot',
    'Slackbot-LinkExpanding',
    'Discordbot',
    'TelegramBot',
    'WhatsApp',
    'redditbot',
    'Mastodon',
];

/**
 * The robots.txt of a client. When it must not be indexed, the share pages
 * and the images of their previews stay open to the crawlers: the link
 * preview robots (Twitterbot…) follow robots.txt, and the search engines are
 * kept out of those pages by their `noindex` robots meta tag instead. With
 * `allowPreviews`, the link preview robots may also read every page but the
 * technical paths, the `noindex` meta tag keeping the search engines out.
 */
export function appRobots({
    allowIndexing,
    allowPreviews = false,
    disallow = [],
}: {
    allowIndexing: boolean;
    allowPreviews?: boolean;
    disallow?: string[];
}): MetadataRoute.Robots {
    const technicalPaths = ['/auth/', '/api/', ...disallow];

    if (allowIndexing) {
        return {
            rules: {
                userAgent: '*',
                allow: ['/', PREVIEW_IMAGES],
                disallow: technicalPaths,
            },
        };
    }

    const closed = {
        userAgent: '*',
        allow: ['/s/', PREVIEW_IMAGES],
        disallow: '/',
    };

    return {
        rules: allowPreviews
            ? [
                  closed,
                  {
                      userAgent: PREVIEW_ROBOTS,
                      allow: ['/', PREVIEW_IMAGES],
                      disallow: technicalPaths,
                  },
              ]
            : closed,
    };
}
