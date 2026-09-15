import {CollectionManageTab} from '@/features/collections/manage/CollectionManageRoute';

export default async function Page({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <CollectionManageTab collectionId={id} tab={tab} />;
}
