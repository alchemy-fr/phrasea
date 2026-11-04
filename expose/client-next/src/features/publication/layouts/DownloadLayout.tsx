'use client';

import {useEffect, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import {DownloadIcon} from 'lucide-react';
import type {Asset, Publication} from '@/types/api';
import {Button} from '@alchemy/next-ui/ui/button';
import {RichText} from '@/components/RichText';
import {useTranslateField} from '@/lib/utils/translate';
import {formatFileSize} from '@/lib/utils/format';
import {cn} from '@alchemy/next-ui/utils/cn';
import {Thumbnail} from '../Thumbnail';
import {useDownload} from '../download';

/**
 * The assets as a list of files to download: the original and each of its
 * renditions. The asset of the URL is brought into view.
 */
export function DownloadLayout({
    publication,
    assets,
    current,
}: {
    publication: Publication;
    assets: Asset[];
    current: Asset | undefined;
}) {
    return (
        <ul data-testid="download-layout" className="flex flex-col gap-3">
            {assets.map(asset => (
                <DownloadItem
                    key={asset.id}
                    publication={publication}
                    asset={asset}
                    highlighted={asset.id === current?.id}
                />
            ))}
        </ul>
    );
}

function DownloadItem({
    publication,
    asset,
    highlighted,
}: {
    publication: Publication;
    asset: Asset;
    highlighted: boolean;
}) {
    const {t, i18n} = useTranslation();
    const translate = useTranslateField();
    const download = useDownload(publication);
    const ref = useRef<HTMLLIElement>(null);
    const title = translate(asset, 'title') || asset.originalName;
    const description = translate(asset, 'description');
    const renditions = (asset.subDefinitions ?? []).filter(s => s.downloadUrl);
    const canDownload = !!publication.downloadEnabled;

    useEffect(() => {
        if (highlighted) {
            ref.current?.scrollIntoView({block: 'center'});
        }
    }, [highlighted]);

    return (
        <li
            ref={ref}
            data-testid="download-item"
            className={cn(
                'flex flex-col gap-4 rounded-lg border bg-card p-4 text-card-foreground sm:flex-row',
                highlighted && 'ring-2 ring-primary'
            )}
        >
            <Thumbnail
                asset={asset}
                className="aspect-square w-full shrink-0 rounded-md sm:w-[200px]"
                imgClassName="object-contain"
            />
            <div className="flex min-w-0 flex-1 flex-col gap-2">
                <h2 className="font-semibold break-words">{title}</h2>
                <p className="text-xs text-muted-foreground">
                    {[
                        asset.originalName !== title
                            ? asset.originalName
                            : undefined,
                        asset.mimeType,
                        asset.size
                            ? formatFileSize(asset.size, true, i18n.language)
                            : undefined,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
                <RichText
                    html={description}
                    className="text-sm text-muted-foreground"
                />
                {canDownload ? (
                    <div className="mt-auto flex flex-wrap gap-2 pt-2">
                        {asset.downloadUrl ? (
                            <Button
                                size="sm"
                                data-testid="download-original"
                                onClick={() =>
                                    download(asset.downloadUrl!, {label: title})
                                }
                            >
                                <DownloadIcon />{' '}
                                {t(
                                    'asset.download_original',
                                    'Download original'
                                )}
                            </Button>
                        ) : null}
                        {renditions.map(rendition => (
                            <Button
                                key={rendition.id}
                                size="sm"
                                variant="outline"
                                data-testid="download-rendition"
                                onClick={() =>
                                    download(rendition.downloadUrl!, {
                                        label: `${title} (${rendition.name})`,
                                    })
                                }
                            >
                                <DownloadIcon />{' '}
                                {t(
                                    'asset.download_rendition',
                                    'Download {{name}}',
                                    {
                                        name: rendition.name ?? rendition.id,
                                    }
                                )}
                            </Button>
                        ))}
                    </div>
                ) : null}
            </div>
        </li>
    );
}
