import {FileManageRoute} from '@/features/files/FileManageRoute';

export default async function Modal({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <FileManageRoute fileId={id} tab={tab} />;
}
