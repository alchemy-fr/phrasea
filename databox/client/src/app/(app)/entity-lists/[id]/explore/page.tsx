import {Suspense} from 'react';
import {EntityListExploreScreen} from '@/features/entities/explore/EntityListExploreScreen';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';

export default async function EntityListExplorePage({
    params,
}: {
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    return (
        <Suspense fallback={<FullPageLoader />}>
            <EntityListExploreScreen listId={id} />
        </Suspense>
    );
}
