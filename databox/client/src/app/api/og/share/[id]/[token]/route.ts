import {proxyImage} from '@alchemy/next-core/server/imageProxy';
import {verifySslFromEnv} from '@alchemy/next-core/server/http';
import {fetchPublicShare, sharePreviewImageUrl} from '@/lib/seo/shareMetadata';

export const dynamic = 'force-dynamic';

/**
 * The image of the link preview of a share: the URL given by the API is
 * signed for a while only.
 */
export async function GET(
    _request: Request,
    {params}: {params: Promise<{id: string; token: string}>}
): Promise<Response> {
    const {id, token} = await params;
    const share = await fetchPublicShare(id, token);
    const url = share ? sharePreviewImageUrl(share) : undefined;
    if (!url) {
        return new Response(null, {status: 404});
    }

    return proxyImage(url, {verifySsl: verifySslFromEnv(process.env)});
}
