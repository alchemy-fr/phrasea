import {type ReactNode} from 'react';
import {SavedSearchManageShell} from '@/features/saved-searches/manage/SavedSearchManageRoute';

export default async function Layout({
    children,
    params,
}: {
    children: ReactNode;
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    return (
        <SavedSearchManageShell savedSearchId={id}>
            {children}
        </SavedSearchManageShell>
    );
}
