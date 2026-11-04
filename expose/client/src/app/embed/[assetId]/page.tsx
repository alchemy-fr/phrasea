import {EmbeddedAssetScreen} from '@/features/embed/EmbeddedAssetScreen';

export default async function EmbeddedAssetPage({
    params,
}: {
    params: Promise<{assetId: string}>;
}) {
    const {assetId} = await params;

    return <EmbeddedAssetScreen assetId={decodeURIComponent(assetId)} />;
}
