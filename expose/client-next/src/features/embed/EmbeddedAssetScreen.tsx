'use client';

import {useTranslation} from 'react-i18next';
import {EyeOffIcon} from 'lucide-react';
import {FullPageLoader} from '@/components/ui/loader';
import {ErrorScreen} from '@/components/ErrorScreen';
import {SecurityScreen} from '@/features/security/SecurityScreen';
import {AssetPlayer} from '@/features/player/AssetPlayer';
import {PublicationCss} from '@/features/publication/PublicationCss';
import {TermsGate} from '@/features/publication/TermsGate';
import {
    useAssetViewLog,
    useEmbeddedAsset,
} from '@/features/publication/usePublication';

/**
 * An asset alone, filling the window, to be embedded in an iframe: the same
 * access control, stylesheet and terms as its publication, then its player.
 */
export function EmbeddedAssetScreen({assetId}: {assetId: string}) {
    const {t} = useTranslation();
    const {data: asset, error, isPending, refetch} = useEmbeddedAsset(assetId);
    const publication = asset?.publication;
    const authorized = !!publication?.authorized;
    useAssetViewLog(authorized ? asset?.id : undefined);

    let content;
    if (isPending) {
        content = <FullPageLoader />;
    } else if (error || !asset || !publication) {
        content = <ErrorScreen error={error} onRetry={() => refetch()} />;
    } else if (!publication.authorized) {
        content = <SecurityScreen publication={publication} reload={refetch} />;
    } else {
        content = (
            <TermsGate publication={publication}>
                <div className="relative h-full">
                    <AssetPlayer asset={asset} className="size-full" />
                    {publication.enabled === false ? (
                        <div
                            data-testid="publication-disabled"
                            className="absolute inset-x-0 top-0 flex items-center justify-center gap-2 bg-warning/90 px-3 py-1.5 text-xs font-medium text-warning-foreground"
                        >
                            <EyeOffIcon className="size-3.5" />
                            {t(
                                'embed.disabled_warning',
                                'Publication of this asset is disabled. Only administrators can see it.'
                            )}
                        </div>
                    ) : null}
                </div>
            </TermsGate>
        );
    }

    return (
        <div
            data-testid="embedded-asset"
            className="h-[100dvh] overflow-hidden"
        >
            {asset?.title ? <title>{asset.title}</title> : null}
            {authorized ? <PublicationCss href={publication?.cssLink} /> : null}
            {content}
        </div>
    );
}
