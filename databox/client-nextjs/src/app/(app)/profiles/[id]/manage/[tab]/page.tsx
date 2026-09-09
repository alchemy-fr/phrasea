import AssetsPage from '@/app/(app)/assets/page';
import {ProfileManageRoute} from '@/features/profiles/manage/ProfileManageRoute';

export default async function Page({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return (
        <>
            <AssetsPage />
            <ProfileManageRoute profileId={id} tab={tab} />
        </>
    );
}
