import type {Metadata, Viewport} from 'next';

/** The brand image of a client (public/icons/og-image.png) */
export const OG_IMAGE = {
    url: '/icons/og-image.png',
    width: 1200,
    height: 630,
};

/** The robots directive of the pages kept out of the search engines */
export const NO_INDEX = {
    index: false,
    follow: false,
} satisfies Metadata['robots'];

export const appViewport: Viewport = {
    width: 'device-width',
    initialScale: 1,
    themeColor: [
        {media: '(prefers-color-scheme: light)', color: '#fafafa'},
        {media: '(prefers-color-scheme: dark)', color: '#1b1c22'},
    ],
};

function parseUrl(value: string | undefined): URL | undefined {
    if (!value) {
        return undefined;
    }
    try {
        return new URL(value);
    } catch {
        return undefined;
    }
}

/**
 * The metadata of the root layout of a client. The manifest link is added by
 * Next from `app/manifest.ts`. `clientUrl` makes the URLs of the link
 * previews absolute.
 */
export function appMetadata({
    name,
    description,
    clientUrl,
    allowIndexing,
}: {
    name: string;
    description: string;
    clientUrl?: string;
    allowIndexing: boolean;
}): Metadata {
    const metadataBase = parseUrl(clientUrl);

    return {
        ...(metadataBase ? {metadataBase} : {}),
        applicationName: name,
        title: {
            default: name,
            template: `%s · ${name}`,
        },
        description,
        icons: {
            icon: [
                {url: '/favicon.ico', sizes: 'any'},
                {url: '/favicon.svg', type: 'image/svg+xml'},
            ],
            apple: '/apple-touch-icon.png',
        },
        appleWebApp: {title: name},
        formatDetection: {telephone: false},
        openGraph: {
            type: 'website',
            siteName: name,
            title: name,
            description,
            images: [{...OG_IMAGE, alt: name}],
        },
        twitter: {card: 'summary_large_image'},
        robots: allowIndexing ? {index: true, follow: true} : NO_INDEX,
    };
}

/**
 * The metadata of a page describing a resource (a publication, a share…).
 * Next replaces the `openGraph` of the parents rather than merging it: the
 * brand image stands in when the resource has none.
 */
export function pageMetadata({
    title,
    description,
    image,
    url,
    siteName,
}: {
    title: string;
    description?: string;
    /** Absolute, or relative to the URL of the client */
    image?: string;
    /** The path of the page */
    url: string;
    siteName: string;
}): Metadata {
    return {
        title,
        ...(description ? {description} : {}),
        openGraph: {
            type: 'website',
            siteName,
            title,
            ...(description ? {description} : {}),
            url,
            images: image
                ? [{url: image, alt: title}]
                : [{...OG_IMAGE, alt: siteName}],
        },
        twitter: {card: image ? 'summary_large_image' : 'summary'},
        alternates: {canonical: url},
    };
}

const ENTITIES: Record<string, string> = {
    amp: '&',
    lt: '<',
    gt: '>',
    quot: '"',
    apos: "'",
    nbsp: ' ',
    hellip: '…',
    laquo: '«',
    raquo: '»',
    rsquo: '’',
    lsquo: '‘',
    rdquo: '”',
    ldquo: '“',
    ndash: '–',
    mdash: '—',
};

function decodeEntities(text: string): string {
    return text.replace(
        /&(#x[0-9a-f]+|#\d+|[a-z]+);/gi,
        (entity, code: string) => {
            if (code[0] === '#') {
                const n =
                    code[1] === 'x' || code[1] === 'X'
                        ? Number.parseInt(code.slice(2), 16)
                        : Number.parseInt(code.slice(1), 10);

                return n > 0 && n <= 0x10ffff ? String.fromCodePoint(n) : '';
            }

            return ENTITIES[code.toLowerCase()] ?? entity;
        }
    );
}

/**
 * The text of an HTML fragment written by an editor, for a description
 * meta tag: no markup, collapsed spaces, cut after a whole word.
 */
export function plainText(html: string, max = 200): string {
    const text = decodeEntities(
        html
            .replace(/<(script|style)\b[\s\S]*?<\/\1\s*>/gi, ' ')
            .replace(/<[^>]*>/g, ' ')
    )
        .replace(/\s+/g, ' ')
        .trim();
    if (text.length <= max) {
        return text;
    }
    const cut = text.slice(0, max - 1);
    const space = cut.lastIndexOf(' ');
    const words = space > max / 2 ? cut.slice(0, space) : cut;

    return `${words.replace(/[\s.,;:!?…-]+$/, '')}…`;
}
