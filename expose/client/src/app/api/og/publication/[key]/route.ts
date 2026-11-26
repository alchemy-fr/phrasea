import type {NextRequest} from 'next/server';
import {proxyImage} from '@alchemy/core/server/imageProxy';
import {verifySslFromEnv} from '@alchemy/core/server/http';
import {
    decodeSegment,
    fetchPublicPublication,
    previewImageUrl,
} from '@/lib/seo/publicationMetadata';

export const dynamic = 'force-dynamic';

/**
 * The cover of a publication, for its link preview: the URL given by the API
 * is signed for a while only.
 */
export async function GET(
    request: NextRequest,
    {params}: {params: Promise<{key: string}>}
): Promise<Response> {
    const key = decodeSegment((await params).key);
    const share = request.nextUrl.searchParams.get('share') ?? undefined;
    const publication = key ? await fetchPublicPublication(key, share) : null;
    const url = previewImageUrl(publication?.cover);
    if (!url) {
        return new Response(null, {status: 404});
    }

    return proxyImage(url, {verifySsl: verifySslFromEnv(process.env)});
}
