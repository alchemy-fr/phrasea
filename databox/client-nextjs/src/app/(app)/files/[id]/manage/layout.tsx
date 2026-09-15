import {type ReactNode} from 'react';
import {FileManageShell} from '@/features/files/FileManageRoute';

export default async function Layout({
    children,
    params,
}: {
    children: ReactNode;
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    return <FileManageShell fileId={id}>{children}</FileManageShell>;
}
