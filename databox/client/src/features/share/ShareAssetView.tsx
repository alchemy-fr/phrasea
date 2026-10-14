'use client';

import {ReactNode, useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    ArrowLeftIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    DownloadIcon,
} from 'lucide-react';
import type {Asset, Share} from '@/types/api';
import {Button} from '@/components/ui/button';
import {SimpleSelect} from '@/components/ui/select';
import {Tooltip} from '@/components/ui/overlays';
import {Separator} from '@/components/ui/misc';
import {FileTypeChip} from '@/components/chips';
import {FilePlayer} from '@/features/assets/player/FilePlayer';
import {AttributeList} from '@/features/attributes/AttributeList';
import {formatFileSize} from '@/lib/utils/format';
import {ShareAttachments} from './ShareAttachments';
import {
    getDefaultViewRendition,
    getRenditionFile,
    type ShareRendition,
} from './shareRenditions';

export type ShareNavigation = {
    index: number;
    total: number;
    onPrev?: () => void;
    onNext?: () => void;
    onClose: () => void;
};

/**
 * A shared asset: its media on the left, and on the right its name, the
 * download button, the rendition displayed and its attributes (copyable,
 * each type in the format of the visitor's choice).
 *
 * With `navigation`, it is the viewer opened from the gallery: a bar to get
 * back to it and to walk the assets (also with the arrow keys and Escape).
 */
export function ShareAssetView({
    share,
    asset,
    renditions,
    onDownload,
    navigation,
    footer,
}: {
    share: Share;
    asset: Asset;
    renditions: ShareRendition[];
    onDownload: () => void;
    navigation?: ShareNavigation;
    /** Rendered at the bottom of the side panel */
    footer?: ReactNode;
}) {
    const {t, i18n} = useTranslation();
    const [viewId, setViewId] = useState<string>();
    const current =
        renditions.find(r => r.id === viewId) ??
        getDefaultViewRendition(asset, renditions);
    const file = current ? getRenditionFile(asset, current) : undefined;
    const attachments = useMemo(
        () => share.attachments?.filter(a => a.assetId === asset.id) ?? [],
        [share.attachments, asset.id]
    );
    const source = asset.source;

    // The rendition picked is the one of the asset displayed
    useEffect(() => setViewId(undefined), [asset.id]);

    useEffect(() => {
        if (!navigation) {
            return;
        }
        const onKey = (e: KeyboardEvent) => {
            const el = document.activeElement as HTMLElement | null;
            if (
                el &&
                (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) ||
                    el.isContentEditable)
            ) {
                return;
            }
            if (document.querySelector('[role=dialog][data-state=open]')) {
                return;
            }
            if (e.key === 'ArrowLeft') {
                navigation.onPrev?.();
            } else if (e.key === 'ArrowRight') {
                navigation.onNext?.();
            } else if (e.key === 'Escape') {
                navigation.onClose();
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [navigation]);

    return (
        <div
            data-testid="share-asset-view"
            className="flex min-h-0 flex-1 flex-col"
        >
            {navigation ? (
                <div className="flex h-11 shrink-0 items-center gap-2 border-b px-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        data-testid="share-view-close"
                        onClick={navigation.onClose}
                    >
                        <ArrowLeftIcon />
                        {t('share.view.back', 'All assets')}
                    </Button>
                    <div className="flex-1" />
                    <span className="text-sm text-muted-foreground tabular-nums">
                        {t('share.view.position', '{{index}} / {{total}}', {
                            index: navigation.index + 1,
                            total: navigation.total,
                        })}
                    </span>
                    <Tooltip content={t('share.view.prev', 'Previous')}>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            data-testid="share-view-prev"
                            aria-label={t('share.view.prev', 'Previous')}
                            disabled={!navigation.onPrev}
                            onClick={navigation.onPrev}
                        >
                            <ChevronLeftIcon />
                        </Button>
                    </Tooltip>
                    <Tooltip content={t('share.view.next', 'Next')}>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            data-testid="share-view-next"
                            aria-label={t('share.view.next', 'Next')}
                            disabled={!navigation.onNext}
                            onClick={navigation.onNext}
                        >
                            <ChevronRightIcon />
                        </Button>
                    </Tooltip>
                </div>
            ) : null}
            <div className="flex min-h-0 flex-1 flex-col overflow-y-auto lg:flex-row lg:overflow-hidden">
                <section className="relative flex h-[60vh] shrink-0 items-center justify-center bg-media-bg lg:h-auto lg:flex-1">
                    {file ? (
                        <FilePlayer
                            key={current?.id}
                            file={file}
                            title={asset.name}
                            fit="zoom"
                            className="size-full"
                        />
                    ) : (
                        <p className="text-sm text-white/70">
                            {t('share.view.no_preview', 'No preview available')}
                        </p>
                    )}
                </section>
                <aside className="flex w-full shrink-0 flex-col gap-4 border-t bg-background p-4 lg:w-[400px] lg:overflow-y-auto lg:border-t-0 lg:border-l">
                    <div className="flex flex-col gap-2">
                        <h1
                            data-testid="share-asset-name"
                            className="text-lg leading-tight font-semibold break-words"
                        >
                            {asset.name || '—'}
                        </h1>
                        {source?.type || source?.size ? (
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                {source.type ? (
                                    <FileTypeChip
                                        mimeType={source.type}
                                        extension={
                                            source.extension || undefined
                                        }
                                    />
                                ) : null}
                                {source.size ? (
                                    <span className="tabular-nums">
                                        {formatFileSize(
                                            source.size,
                                            true,
                                            i18n.language
                                        )}
                                    </span>
                                ) : null}
                            </div>
                        ) : null}
                    </div>
                    <div className="flex flex-col gap-2">
                        <Button
                            data-testid="share-download"
                            disabled={renditions.length === 0}
                            onClick={onDownload}
                        >
                            <DownloadIcon />
                            {t('share.download.button', 'Download…')}
                        </Button>
                        {renditions.length > 1 ? (
                            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                <span className="shrink-0">
                                    {t('share.view.displayed', 'Displayed')}
                                </span>
                                <SimpleSelect
                                    size="sm"
                                    className="min-w-0 flex-1"
                                    value={current?.id}
                                    onValueChange={setViewId}
                                    options={renditions.map(r => ({
                                        value: r.id,
                                        label: r.label,
                                    }))}
                                />
                            </label>
                        ) : null}
                    </div>
                    <Separator />
                    <section>
                        <h2 className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {t('share.view.attributes', 'Information')}
                        </h2>
                        <AttributeList asset={asset} controls ignoreProfile />
                    </section>
                    {attachments.length > 0 ? (
                        <>
                            <Separator />
                            <ShareAttachments attachments={attachments} />
                        </>
                    ) : null}
                    {footer ? (
                        <div className="mt-auto flex flex-col gap-4">
                            {footer}
                        </div>
                    ) : null}
                </aside>
            </div>
        </div>
    );
}
