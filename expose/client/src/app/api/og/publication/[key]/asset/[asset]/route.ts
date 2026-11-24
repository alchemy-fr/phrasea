import {proxyImage} from '@alchemy/next-core/server/imageProxy';
import {verifySslFromEnv} from '@alchemy/next-core/server/http';
import {findAsset} from '@/features/publication/assets';
import {
    decodeSegment,
    fetchPublicPublication,
    previewImageUrl,
} from '@/lib/seo/publicationMetadata';

export const dynamic = 'force-dynamic';

/**
 * The thumbnail of an asset of a publication, for its link preview: the URL
 * given by the API is signed for a while only.
 */
export async function GET(
    _request: Request,
    {params}: {params: Promise<{key: string; asset: string}>}
): Promise<Response> {
    const {key: segment, asset: assetId} = await params;
    const key = decodeSegment(segment);
    const publication = key ? await fetchPublicPublication(key) : null;
    let url: string | undefined;
    try {
        url = previewImageUrl(findAsset(publication?.assets ?? [], assetId));
    } catch {
        // A malformed URL segment
    }
    if (!url) {
        return new Response(null, {status: 404});
    }

    return proxyImage(url, {verifySsl: verifySslFromEnv(process.env)});
}
