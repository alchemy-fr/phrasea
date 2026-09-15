import {WorkspaceManageTab} from '@/features/workspaces/manage/WorkspaceManageRoute';

export default async function Page({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <WorkspaceManageTab workspaceId={id} tab={tab} />;
}
