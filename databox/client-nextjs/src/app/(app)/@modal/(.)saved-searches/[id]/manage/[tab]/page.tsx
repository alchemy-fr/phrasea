import {SavedSearchManageRoute} from '@/features/saved-searches/manage/SavedSearchManageRoute';

export default async function Modal({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <SavedSearchManageRoute savedSearchId={id} tab={tab} />;
}
