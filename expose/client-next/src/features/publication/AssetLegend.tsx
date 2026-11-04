'use client';

import {useTranslation} from 'react-i18next';
import {DownloadIcon} from 'lucide-react';
import type {Asset, Publication} from '@/types/api';
import {Button} from '@alchemy/next-ui/ui/button';
import {RichText} from '@/components/RichText';
import {useTranslateField} from '@/lib/utils/translate';
import {cn} from '@alchemy/next-ui/utils/cn';
import {useDownload} from './download';

/** Title, description and download button of the asset displayed */
export function AssetLegend({
    publication,
    asset,
    className,
}: {
    publication: Publication;
    asset: Asset;
    className?: string;
}) {
    const {t} = useTranslation();
    const translate = useTranslateField();
    const download = useDownload(publication);
    const title = translate(asset, 'title');
    const description = translate(asset, 'description');

    return (
        <div
            data-testid="asset-legend"
            className={cn('flex flex-col gap-3', className)}
        >
            {title ? (
                <h2
                    data-testid="asset-title"
                    className="text-lg font-semibold break-words"
                >
                    {title}
                </h2>
            ) : null}
            <RichText
                html={description}
                className="text-sm text-muted-foreground"
            />
            {publication.downloadEnabled && asset.downloadUrl ? (
                <div>
                    <Button
                        variant="outline"
                        size="sm"
                        data-testid="asset-download"
                        onClick={() =>
                            download(asset.downloadUrl!, {
                                label: title || asset.originalName,
                            })
                        }
                    >
                        <DownloadIcon /> {t('asset.download', 'Download')}
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
