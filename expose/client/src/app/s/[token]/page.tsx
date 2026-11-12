import {ShareLinkScreen} from '@/features/share/ShareLinkScreen';

export default async function ShareLinkPage({
    params,
}: {
    params: Promise<{token: string}>;
}) {
    const {token} = await params;

    return <ShareLinkScreen token={decodeURIComponent(token)} />;
}
