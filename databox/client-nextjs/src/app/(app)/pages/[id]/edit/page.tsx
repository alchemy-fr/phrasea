import {PageEditScreen} from '@/features/cms/PageEditScreen';

export default async function PageEdit({
    params,
}: {
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    return <PageEditScreen pageId={id} />;
}
