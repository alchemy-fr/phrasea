import type {Metadata} from 'next';
import {PublicationScreen} from '@/features/publication/PublicationScreen';
import {
    decodeSegment,
    fetchPublicPublication,
    publicationPageMetadata,
} from '@/lib/seo/publicationMetadata';

type Params = Promise<{publication: string}>;

/** The link preview of a public publication, the app's otherwise */
export async function generateMetadata({
    params,
}: {
    params: Params;
}): Promise<Metadata> {
    const key = decodeSegment((await params).publication);
    const publication = key ? await fetchPublicPublication(key) : null;

    return publication ? publicationPageMetadata(publication) : {};
}

/**
 * The publication is rendered by the layout, kept while moving from one of
 * its assets to another (the pages only change the asset of the URL): the
 * grid, its scroll and the media loaded stay as they are.
 */
export default async function PublicationLayout({
    params,
    children,
}: {
    params: Params;
    children: React.ReactNode;
}) {
    const {publication} = await params;

    return (
        <>
            <PublicationScreen
                publicationKey={decodeURIComponent(publication)}
            />
            {children}
        </>
    );
}
