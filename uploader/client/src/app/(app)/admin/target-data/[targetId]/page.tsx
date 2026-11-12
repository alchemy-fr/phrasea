import {TargetDataEditScreen} from '@/features/admin/TargetDataScreens';

export default async function TargetDataEditPage({
    params,
}: {
    params: Promise<{targetId: string}>;
}) {
    const {targetId} = await params;

    return <TargetDataEditScreen key={targetId} targetId={targetId} />;
}
