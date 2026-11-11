import {Suspense} from 'react';
import {EntityListManageScreen} from '@/features/entities/manage/EntityListManageScreen';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';

export default async function EntityListManagePage({
    params,
}: {
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    return (
        <Suspense fallback={<FullPageLoader />}>
            <EntityListManageScreen listId={id} />
        </Suspense>
    );
}
