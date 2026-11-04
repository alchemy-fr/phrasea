import {PublicationScreen} from '@/features/publication/PublicationScreen';

/**
 * The publication is rendered by the layout, kept while moving from one of
 * its assets to another (the pages only change the asset of the URL): the
 * grid, its scroll and the media loaded stay as they are.
 */
export default async function PublicationLayout({
    params,
    children,
}: {
    params: Promise<{publication: string}>;
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
