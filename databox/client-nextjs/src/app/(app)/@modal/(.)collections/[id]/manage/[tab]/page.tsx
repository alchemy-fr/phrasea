import {CollectionManageRoute} from '@/features/collections/manage/CollectionManageRoute';

export default async function Modal({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <CollectionManageRoute collectionId={id} tab={tab} />;
}
