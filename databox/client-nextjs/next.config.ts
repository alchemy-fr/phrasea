import type {NextConfig} from 'next';

const nextConfig: NextConfig = {
    output: 'standalone',
    reactStrictMode: true,
    poweredByHeader: false,
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
};

export default nextConfig;
