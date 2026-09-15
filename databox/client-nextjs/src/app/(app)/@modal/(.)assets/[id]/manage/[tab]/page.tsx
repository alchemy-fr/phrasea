import {AssetManageTab} from '@/features/assets/manage/AssetManageRoute';

export default async function Page({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <AssetManageTab assetId={id} tab={tab} />;
}
