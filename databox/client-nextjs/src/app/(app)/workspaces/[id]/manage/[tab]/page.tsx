import AssetsPage from '@/app/(app)/assets/page';
import {WorkspaceManageRoute} from '@/features/workspaces/manage/WorkspaceManageRoute';

export default async function Page({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return (
        <>
            <AssetsPage />
            <WorkspaceManageRoute workspaceId={id} tab={tab} />
        </>
    );
}
