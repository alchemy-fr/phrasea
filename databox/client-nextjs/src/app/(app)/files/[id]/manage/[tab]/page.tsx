import AssetsPage from '@/app/(app)/assets/page';
import {FileManageRoute} from '@/features/files/FileManageRoute';

export default async function Page({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return (
        <>
            <AssetsPage />
            <FileManageRoute fileId={id} tab={tab} />
        </>
    );
}
