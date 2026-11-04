import type {NextConfig} from 'next';

// `next dev` is reached through the traefik proxy (e.g.
// https://uploader.phrasea.local), never on localhost: without this,
// Next 16 blocks the /_next/hmr websocket as a cross-origin dev request and
// the page reload-loops. Derived from the URL the client is served at, plus
// anything listed in NEXT_ALLOWED_DEV_ORIGINS (comma separated).
const allowedDevOrigins = [
    ...[process.env.UPLOADER_CLIENT_URL].map(url => {
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
    experimental: {
        optimizePackageImports: ['lucide-react', 'radix-ui'],
    },
    turbopack: {},
    // Workspace libraries are shipped as TypeScript sources
    transpilePackages: [
        '@alchemy/api',
        '@alchemy/next-core',
        '@alchemy/next-i18n',
        '@alchemy/next-theme',
        '@alchemy/next-ui',
    ],
};

export default nextConfig;
