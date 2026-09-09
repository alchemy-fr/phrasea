import AssetsPage from '@/app/(app)/assets/page';
import {CollectionManageRoute} from '@/features/collections/manage/CollectionManageRoute';

export default async function Page({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return (
        <>
            <AssetsPage />
            <CollectionManageRoute collectionId={id} tab={tab} />
        </>
    );
}
