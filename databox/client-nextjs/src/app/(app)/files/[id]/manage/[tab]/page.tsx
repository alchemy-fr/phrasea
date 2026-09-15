import {FileManageTab} from '@/features/files/FileManageRoute';

export default async function Page({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <FileManageTab fileId={id} tab={tab} />;
}
