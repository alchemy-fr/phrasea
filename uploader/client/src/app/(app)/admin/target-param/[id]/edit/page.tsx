import {TargetParamsEditScreen} from '@/features/admin/TargetParamsScreens';

export default async function TargetParamsEditPage({
    params,
}: {
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    return <TargetParamsEditScreen key={id} id={id} />;
}
