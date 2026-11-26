import type {Metadata} from 'next';
import {NO_INDEX} from '@alchemy/core/seo/metadata';
import {
    assetPageMetadata,
    decodeSegment,
    fetchPublicationMetadata,
} from '@/lib/seo/publicationMetadata';

/** The link preview of an asset of a public publication */
export async function generateMetadata({
    params,
}: {
    params: Promise<{publication: string; asset: string}>;
}): Promise<Metadata> {
    const {publication: segment, asset} = await params;
    const key = decodeSegment(segment);
    const publication = key ? await fetchPublicationMetadata(key) : null;

    return publication
        ? assetPageMetadata(publication, asset)
        : {robots: NO_INDEX};
}

/** See the layout of the publication, which reads the asset of the URL */
export default function PublicationAssetPage() {
    return null;
}
