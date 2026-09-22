import type {NextConfig} from 'next';

// `next dev` is reached through the traefik/nginx proxy (e.g.
// https://databox-next.phrasea.local), never on localhost: without this, Next 16
// blocks the /_next/hmr websocket as a cross-origin dev request and the page
// reload-loops. Derived from the URL the client is served at, plus anything
// listed in NEXT_ALLOWED_DEV_ORIGINS (comma separated).
const allowedDevOrigins = [
    ...[process.env.DATABOX_NEXT_CLIENT_URL].map(url => {
        try {
            return new URL(url!).hostname;
        } catch {
            return undefined;
        }
    }),
    ...(process.env.NEXT_ALLOWED_DEV_ORIGINS ?? '').split(','),
]
    .map(origin => origin?.trim())
    .filter((origin): origin is string => !!origin);

const nextConfig: NextConfig = {
    output: 'standalone',
    reactStrictMode: true,
    poweredByHeader: false,
    allowedDevOrigins,
    // Runtime configuration is read from process.env at request time in the
    // root layout (see src/lib/config/server.ts), so no build-time env baking.
    images: {
        // Thumbnails are served from S3/MinIO signed URLs: bypass the image
        // optimizer, the API already generates renditions at the right size.
        unoptimized: true,
    },
    experimental: {
        optimizePackageImports: ['lucide-react', 'radix-ui', 'date-fns'],
    },
    turbopack: {},
    async redirects() {
        return [
            {
                // The asset manage dialog moved into the side panel of the
                // viewer: former URLs (links, bookmarks, notifications) land
                // on the matching panel tab. Redirected at the routing layer
                // rather than from a page, so that no React tree is rendered
                // just to be thrown away.
                source: '/assets/:id/manage/:tab',
                destination: '/assets/:id/_#panel=:tab',
                permanent: false,
            },
        ];
    },
};

export default nextConfig;
