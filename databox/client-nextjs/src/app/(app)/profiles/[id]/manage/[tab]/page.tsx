import {ProfileManageTab} from '@/features/profiles/manage/ProfileManageRoute';

export default async function Page({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <ProfileManageTab profileId={id} tab={tab} />;
}
