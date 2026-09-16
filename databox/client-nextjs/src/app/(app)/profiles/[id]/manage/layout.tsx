import {type ReactNode} from 'react';
import {ProfileManageShell} from '@/features/profiles/manage/ProfileManageRoute';

/** The dialog and its tabs: see `TabbedRouteDialogShell`. */
export default async function Layout({
    children,
    params,
}: {
    children: ReactNode;
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    return (
        <>
            <ProfileManageShell profileId={id} />
            {children}
        </>
    );
}
