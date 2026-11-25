import {TargetAdminEditScreen} from '@/features/admin/TargetAdminScreens';

export default async function TargetEditPage({
    params,
}: {
    params: Promise<{targetId: string}>;
}) {
    const {targetId} = await params;

    return <TargetAdminEditScreen key={targetId} targetId={targetId} />;
}
