'use client';

import {useTranslation} from 'react-i18next';
import {ImageOffIcon, SearchXIcon} from 'lucide-react';
import {Layout, type Publication} from '@/types/api';
import {Alert, EmptyState} from '@/components/ui/misc';
import {PublicationHeader} from './PublicationHeader';
import {GridLayout} from './layouts/GridLayout';
import {GalleryLayout} from './layouts/GalleryLayout';
import {DownloadLayout} from './layouts/DownloadLayout';
import {findAsset} from './assets';

/** The content of a publication the user has access to */
export function PublicationView({
    publication,
    assetKey,
}: {
    publication: Publication;
    /** The asset of the URL (id or slug) */
    assetKey?: string;
}) {
    const {t} = useTranslation();
    const assets = publication.assets ?? [];
    const current = findAsset(assets, assetKey);
    const layout = publication.layout ?? Layout.Grid;

    let content;
    if (assets.length === 0) {
        content = (
            <EmptyState
                testId="no-asset"
                icon={<ImageOffIcon />}
                title={t('publication.no_asset', 'No asset available')}
            />
        );
    } else if (layout === Layout.Gallery) {
        content = (
            <GalleryLayout
                publication={publication}
                assets={assets}
                current={current}
            />
        );
    } else if (layout === Layout.Download) {
        content = (
            <DownloadLayout
                publication={publication}
                assets={assets}
                current={current}
            />
        );
    } else {
        // The map layout is not available: the assets are shown as a grid
        content = (
            <GridLayout
                publication={publication}
                assets={assets}
                current={current}
            />
        );
    }

    return (
        <div
            data-testid="publication"
            data-layout={layout}
            className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6"
        >
            <PublicationHeader publication={publication} />
            {assetKey && !current ? (
                <Alert
                    variant="warning"
                    icon={<SearchXIcon />}
                    data-testid="asset-not-found"
                >
                    {t(
                        'publication.asset_not_found',
                        'The asset you are looking for is not part of this publication.'
                    )}
                </Alert>
            ) : null}
            {content}
        </div>
    );
}
