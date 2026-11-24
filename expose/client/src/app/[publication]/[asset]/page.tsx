import type {Metadata} from 'next';
import {
    assetPageMetadata,
    decodeSegment,
    fetchPublicPublication,
} from '@/lib/seo/publicationMetadata';

/** The link preview of an asset of a public publication */
export async function generateMetadata({
    params,
}: {
    params: Promise<{publication: string; asset: string}>;
}): Promise<Metadata> {
    const {publication: segment, asset} = await params;
    const key = decodeSegment(segment);
    const publication = key ? await fetchPublicPublication(key) : null;

    return publication ? assetPageMetadata(publication, asset) : {};
}

/** See the layout of the publication, which reads the asset of the URL */
export default function PublicationAssetPage() {
    return null;
}
