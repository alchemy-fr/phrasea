import {FormSchemaEditScreen} from '@/features/admin/FormSchemaScreens';

export default async function FormSchemaEditPage({
    params,
}: {
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    return <FormSchemaEditScreen key={id} id={id} />;
}
