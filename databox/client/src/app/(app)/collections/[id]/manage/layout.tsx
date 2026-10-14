import {type ReactNode} from 'react';
import {CollectionManageShell} from '@/features/collections/manage/CollectionManageRoute';

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
            <CollectionManageShell collectionId={id} />
            {children}
        </>
    );
}
