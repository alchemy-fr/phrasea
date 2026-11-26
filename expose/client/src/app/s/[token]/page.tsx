import type {Metadata} from 'next';
import {NO_INDEX, pageMetadata} from '@alchemy/core/seo/metadata';
import {ShareLinkScreen} from '@/features/share/ShareLinkScreen';
import {routes} from '@/lib/routes';
import {APP_NAME} from '@/lib/seo/app';
import {
    decodeSegment,
    fetchPublicPublication,
    fetchShareResolution,
    publicationPageMetadata,
    toPublicationMetadata,
} from '@/lib/seo/publicationMetadata';

/**
 * The link preview of the shared publication. The URL carries the token:
 * not to be indexed.
 */
export async function generateMetadata({
    params,
}: {
    params: Promise<{token: string}>;
}): Promise<Metadata> {
    const token = decodeSegment((await params).token);
    const resolution = token ? await fetchShareResolution(token) : null;
    if (!token || !resolution?.publication.title) {
        return {robots: NO_INDEX};
    }
    const url = routes.share(token);
    const publication = await fetchPublicPublication(
        resolution.publication.id,
        token
    );

    return {
        ...(publication
            ? publicationPageMetadata(toPublicationMetadata(publication), {
                  url,
                  shareToken: token,
              })
            : pageMetadata({
                  title: resolution.publication.title,
                  url,
                  siteName: APP_NAME,
              })),
        robots: NO_INDEX,
    };
}

export default async function ShareLinkPage({
    params,
}: {
    params: Promise<{token: string}>;
}) {
    const {token} = await params;

    return <ShareLinkScreen token={decodeURIComponent(token)} />;
}
