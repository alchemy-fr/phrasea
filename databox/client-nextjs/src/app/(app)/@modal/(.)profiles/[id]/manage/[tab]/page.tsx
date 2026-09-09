import {ProfileManageRoute} from '@/features/profiles/manage/ProfileManageRoute';

export default async function Modal({
    params,
}: {
    params: Promise<{id: string; tab: string}>;
}) {
    const {id, tab} = await params;

    return <ProfileManageRoute profileId={id} tab={tab} />;
}
