import {FormEditScreen} from '@/features/admin/FormScreens';

export default async function FormEditPage({
    params,
}: {
    params: Promise<{targetId: string}>;
}) {
    const {targetId} = await params;

    return <FormEditScreen key={targetId} targetId={targetId} />;
}
