import {SharePage} from '@/features/share/SharePage';

export default async function PublicSharePage({
    params,
}: {
    params: Promise<{id: string; token: string}>;
}) {
    const {id, token} = await params;

    return <SharePage id={id} token={token} />;
}
