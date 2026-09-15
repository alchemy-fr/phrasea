import {SavedSearchManageTab} from '@/features/saved-searches/manage/SavedSearchManageRoute';

export default async function Page({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <SavedSearchManageTab savedSearchId={id} tab={tab} />;
}
