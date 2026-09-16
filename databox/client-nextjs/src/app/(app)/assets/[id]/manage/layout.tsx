import {type ReactNode} from 'react';
import {AssetManageShell} from '@/features/assets/manage/AssetManageRoute';

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
            <AssetManageShell assetId={id} />
            {children}
        </>
    );
}
