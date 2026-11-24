import type {Metadata} from 'next';
import {SharePage} from '@/features/share/SharePage';
import {fetchPublicShare, sharePageMetadata} from '@/lib/seo/shareMetadata';

type Params = Promise<{id: string; token: string}>;

/** The link preview of the share, the app's when it cannot be read */
export async function generateMetadata({
    params,
}: {
    params: Params;
}): Promise<Metadata> {
    const {id, token} = await params;

    return sharePageMetadata(id, token, await fetchPublicShare(id, token));
}

export default async function PublicSharePage({params}: {params: Params}) {
    const {id, token} = await params;

    return <SharePage id={id} token={token} />;
}
