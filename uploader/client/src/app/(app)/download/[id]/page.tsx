import {DownloadScreen} from '@/features/download/DownloadScreen';

export default async function DownloadPage({
    params,
}: {
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    return <DownloadScreen key={id} targetId={id} />;
}
