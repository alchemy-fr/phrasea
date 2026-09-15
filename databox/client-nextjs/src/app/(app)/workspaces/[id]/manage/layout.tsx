import {type ReactNode} from 'react';
import {WorkspaceManageShell} from '@/features/workspaces/manage/WorkspaceManageRoute';

export default async function Layout({
    children,
    params,
}: {
    children: ReactNode;
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    return (
        <WorkspaceManageShell workspaceId={id}>{children}</WorkspaceManageShell>
    );
}
