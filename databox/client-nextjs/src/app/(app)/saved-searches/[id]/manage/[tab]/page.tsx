import AssetsPage from '@/app/(app)/assets/page';
import {SavedSearchManageRoute} from '@/features/saved-searches/manage/SavedSearchManageRoute';

export default async function Page({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return (
        <>
            <AssetsPage />
            <SavedSearchManageRoute savedSearchId={id} tab={tab} />
        </>
    );
}
