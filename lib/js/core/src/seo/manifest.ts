import type {MetadataRoute} from 'next';

/** The web app manifest of a client, served by its `app/manifest.ts` */
export function appManifest({
    name,
    shortName,
    description,
}: {
    name: string;
    shortName: string;
    description: string;
}): MetadataRoute.Manifest {
    return {
        id: '/',
        name,
        short_name: shortName,
        description,
        start_url: '/',
        scope: '/',
        display: 'standalone',
        background_color: '#fafafa',
        theme_color: '#fafafa',
        icons: [
            {src: '/favicon.svg', sizes: 'any', type: 'image/svg+xml'},
            {src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png'},
            {src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png'},
            {
                src: '/icons/icon-maskable-512.png',
                sizes: '512x512',
                type: 'image/png',
                purpose: 'maskable',
            },
        ],
    };
}
