import {WorkspaceManageRoute} from '@/features/workspaces/manage/WorkspaceManageRoute';

export default async function Modal({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <WorkspaceManageRoute workspaceId={id} tab={tab} />;
}
