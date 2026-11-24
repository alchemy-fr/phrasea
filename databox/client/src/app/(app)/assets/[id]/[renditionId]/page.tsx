import AssetsPage from '../../page';
import {AssetViewRoute} from '@/features/assets/view/AssetViewRoute';

/**
 * Hard navigation to an asset: the search screen is rendered behind the
 * viewer so closing it lands on a usable page.
 */
export default async function AssetViewPage({
    params,
    searchParams,
}: {
    params: Promise<{id: string; renditionId: string}>;
    searchParams: Promise<{story?: string | string[]}>;
}) {
    const {id, renditionId} = await params;
    const {story} = await searchParams;

    return (
        <>
            <AssetsPage />
            <AssetViewRoute
                assetId={id}
                renditionId={renditionId}
                storyId={typeof story === 'string' && story ? story : undefined}
            />
        </>
    );
}
