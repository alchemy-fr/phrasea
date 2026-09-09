import {AssetManageRoute} from '@/features/assets/manage/AssetManageRoute';

export default async function AssetManageModal({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <AssetManageRoute assetId={id} tab={tab} />;
}
