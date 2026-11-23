import {AssetViewRoute} from '@/features/assets/view/AssetViewRoute';

export default async function AssetViewModal({
    params,
    searchParams,
}: {
    params: Promise<{id: string; renditionId: string}>;
    searchParams: Promise<{story?: string | string[]}>;
}) {
    const {id, renditionId} = await params;
    const {story} = await searchParams;

    return (
        <AssetViewRoute
            assetId={id}
            renditionId={renditionId}
            storyId={typeof story === 'string' && story ? story : undefined}
        />
    );
}
