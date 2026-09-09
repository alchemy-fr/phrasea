import {Suspense} from 'react';
import {AssetSearchScreen} from '@/features/search/AssetSearchScreen';
import {FullPageLoader} from '@/components/ui/loader';

export default function AssetsPage() {
    return (
        <Suspense fallback={<FullPageLoader />}>
            <AssetSearchScreen />
        </Suspense>
    );
}
