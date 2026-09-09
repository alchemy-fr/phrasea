'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {useSearchParams} from 'next/navigation';
import {DownloadIcon, ImagesIcon} from 'lucide-react';
import {getPublicShare} from '@/lib/api/misc';
import {FullPageLoader} from '@/components/ui/loader';
import {EmptyState} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {SimpleSelect} from '@/components/ui/select';
import {FilePlayer} from '@/features/assets/player/FilePlayer';
import {AttributeList} from '@/features/attributes/AttributeList';
import type {ApiFile} from '@/types/api';

/**
 * Public page of a shared asset (`/s/:id/:token`): title, player and
 * attributes. Supports `?embed=1` for iframe embedding.
 */
export function SharePage({id, token}: {id: string; token: string}) {
    const {t} = useTranslation();
    const searchParams = useSearchParams();
    const embed = searchParams.get('embed') === '1';
    const share = useQuery({
        queryKey: ['public-share', id, token],
        queryFn: () => getPublicShare(id, token),
    });
    const [renditionUrl, setRenditionUrl] = useState<string>();

    const files = useMemo(() => {
        const asset = share.data?.asset;
        if (!asset) {
            return [];
        }
        const list: {label: string; file: ApiFile}[] = [];
        if (asset.preview?.file)
            list.push({
                label: t('share.rendition.preview', 'Preview'),
                file: asset.preview.file,
            });
        if (
            asset.main?.file &&
            asset.main.file.url !== asset.preview?.file?.url
        )
            list.push({
                label: t('share.rendition.main', 'Main'),
                file: asset.main.file,
            });
        if (asset.source?.url)
            list.push({
                label: t('share.rendition.source', 'Source'),
                file: asset.source,
            });
        share.data?.alternateUrls?.forEach(a =>
            list.push({
                label: a.name,
                file: {
                    id: a.url,
                    url: a.url,
                    type: a.type ?? '',
                    extension: '',
                    alternateUrls: [],
                    size: 0,
                    docUniqueId: '',
                    checksum: '',
                    fileName: a.name,
                    analysisPending: false,
                },
            })
        );

        return list;
    }, [share.data, t]);

    if (share.isLoading) {
        return <FullPageLoader />;
    }
    if (share.isError || !share.data?.asset) {
        return (
            <EmptyState
                className="h-full"
                icon={<ImagesIcon />}
                title={t(
                    'share.not_found',
                    'This link is not valid or has expired'
                )}
            />
        );
    }
    const asset = share.data.asset;
    const current = files.find(f => f.file.url === renditionUrl) ?? files[0];

    return (
        <div className="flex h-full flex-col overflow-y-auto bg-background">
            {!embed ? (
                <header className="flex items-center gap-3 border-b px-4 py-3">
                    <ImagesIcon className="size-5 text-primary" />
                    <h1 className="min-w-0 flex-1 truncate text-lg font-semibold">
                        {asset.name}
                    </h1>
                    {files.length > 1 ? (
                        <SimpleSelect
                            size="sm"
                            className="w-40"
                            value={current?.file.url}
                            onValueChange={setRenditionUrl}
                            options={files.map(f => ({
                                value: f.file.url!,
                                label: f.label,
                            }))}
                        />
                    ) : null}
                    {current?.file.url ? (
                        <Button size="sm" asChild>
                            <a
                                href={current.file.url}
                                download
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <DownloadIcon />{' '}
                                {t('asset.actions.download', 'Download')}
                            </a>
                        </Button>
                    ) : null}
                </header>
            ) : null}
            <div
                className={
                    embed
                        ? 'flex h-full items-center justify-center bg-media-bg'
                        : 'grid flex-1 gap-6 p-4 lg:grid-cols-[2fr_1fr]'
                }
            >
                <div className="flex min-h-[50vh] items-center justify-center rounded-lg bg-media-bg">
                    {current ? (
                        <FilePlayer
                            file={current.file}
                            title={asset.name}
                            autoPlay={embed}
                            className="max-h-[80vh]"
                        />
                    ) : null}
                </div>
                {!embed ? (
                    <aside>
                        <AttributeList asset={asset} />
                    </aside>
                ) : null}
            </div>
        </div>
    );
}
