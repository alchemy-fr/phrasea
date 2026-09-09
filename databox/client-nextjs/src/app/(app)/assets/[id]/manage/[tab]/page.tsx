import AssetsPage from '../../../page';
import {AssetManageRoute} from '@/features/assets/manage/AssetManageRoute';

export default async function AssetManagePage({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return (
        <>
            <AssetsPage />
            <AssetManageRoute assetId={id} tab={tab} />
        </>
    );
}
