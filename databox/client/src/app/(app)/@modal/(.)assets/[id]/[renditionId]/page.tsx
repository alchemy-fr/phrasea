import {AssetViewRoute} from '@/features/assets/view/AssetViewRoute';

export default async function AssetViewModal({
    params,
}: {
    params: Promise<{id: string; renditionId: string}>;
}) {
    const {id, renditionId} = await params;

    return <AssetViewRoute assetId={id} renditionId={renditionId} />;
}
