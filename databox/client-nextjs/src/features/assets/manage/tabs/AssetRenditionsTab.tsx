'use client';

import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {
    DownloadIcon,
    ImagePlusIcon,
    LockIcon,
    RefreshCwIcon,
    SaveIcon,
    Trash2Icon,
    UploadIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {AssetRendition, RenditionDefinition} from '@/types/api';
import {AssetType} from '@/types/api';
import type {AssetTabProps} from '../AssetManageRoute';
import {
    deleteRendition,
    getAssetRenditions,
    getRenditionDefinitions,
} from '@/lib/api/misc';
import {Button} from '@/components/ui/button';
import {Badge, Skeleton} from '@/components/ui/misc';
import {Tooltip} from '@/components/ui/overlays';
import {FilePlayer} from '@/features/assets/player/FilePlayer';
import {formatFileSize} from '@/lib/utils/format';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {UploadRenditionDialog} from '../dialogs/UploadRenditionDialog';
import {CreateDynamicRenditionDialog} from '../dialogs/CreateDynamicRenditionDialog';
import {SaveAsDialog} from '@/features/assets/actions/SaveAsDialog';

export function AssetRenditionsTab({asset}: AssetTabProps) {
    const {t, i18n} = useTranslation();
    const {openModal} = useModals();
    const queryClient = useQueryClient();
    const queryKey = ['asset-renditions', asset.id];
    const query = useQuery({
        queryKey,
        queryFn: async () => {
            const [renditions, definitions] = await Promise.all([
                getAssetRenditions(asset.id),
                getRenditionDefinitions({
                    workspaceIds: [asset.workspace.id],
                    assetId: asset.id,
                    target: asset.storyCollection
                        ? AssetType.Story
                        : AssetType.Asset,
                }),
            ]);

            return {renditions, definitions: definitions.items};
        },
    });
    const refresh = () => queryClient.invalidateQueries({queryKey});
    useChannelEvent(
        'assets',
        'rendition-update',
        (e: {assetId: string}) => e.assetId === asset.id && refresh()
    );

    if (query.isLoading) {
        return (
            <div className="space-y-3">
                {[...Array(3)].map((_, i) => (
                    <Skeleton key={i} className="h-28" />
                ))}
            </div>
        );
    }
    const renditions = query.data?.renditions ?? [];
    const definitions = query.data?.definitions ?? [];
    const missing = definitions.filter(
        d => !renditions.some(r => r.definition?.id === d.id)
    );

    return (
        <div className="space-y-3">
            <div className="flex justify-end">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        openModal(CreateDynamicRenditionDialog, {
                            asset,
                            renditions,
                            definitions,
                            onCreated: refresh,
                        })
                    }
                >
                    <ImagePlusIcon />{' '}
                    {t('rendition.create_custom', 'Create custom rendition')}
                </Button>
            </div>
            {renditions.map(r => (
                <RenditionCard
                    key={r.id}
                    rendition={r}
                    asset={asset}
                    lang={i18n.language}
                    onChanged={refresh}
                />
            ))}
            {missing.map(d => (
                <div
                    key={d.id}
                    className="flex items-center gap-3 rounded-md border border-dashed p-3 text-sm text-muted-foreground"
                >
                    <span className="flex-1">
                        {d.displayName ?? d.name} —{' '}
                        {t('rendition.not_generated', 'not generated yet')}
                    </span>
                    {d.substitutable ? (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                openModal(UploadRenditionDialog, {
                                    asset,
                                    definition: d,
                                    onUploaded: refresh,
                                })
                            }
                        >
                            <UploadIcon /> {t('rendition.upload', 'Upload')}
                        </Button>
                    ) : null}
                </div>
            ))}
        </div>
    );
}

function RenditionCard({
    rendition,
    asset,
    lang,
    onChanged,
}: {
    rendition: AssetRendition;
    asset: AssetTabProps['asset'];
    lang: string;
    onChanged: () => void;
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const file = rendition.file;

    return (
        <div className="flex gap-3 rounded-md border p-3">
            <div className="flex size-32 shrink-0 items-center justify-center overflow-hidden rounded bg-media-bg">
                {file ? (
                    <FilePlayer file={file} controls={false} />
                ) : (
                    <RefreshCwIcon className="size-6 animate-spin text-muted-foreground" />
                )}
            </div>
            <div className="min-w-0 flex-1 space-y-1 text-sm">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">
                        {rendition.displayName ?? rendition.name}
                    </span>
                    {!rendition.ready ? (
                        <Badge variant="warning">
                            {t('rendition.pending', 'Generating…')}
                        </Badge>
                    ) : null}
                    {rendition.locked ? (
                        <Badge variant="muted">
                            <LockIcon /> {t('rendition.locked', 'Locked')}
                        </Badge>
                    ) : null}
                    {rendition.substituted ? (
                        <Badge variant="secondary">
                            {t('rendition.substituted', 'Substituted')}
                        </Badge>
                    ) : null}
                    {rendition.projection ? (
                        <Badge variant="secondary">
                            {t('rendition.projection', 'Projection')}
                        </Badge>
                    ) : null}
                    {rendition.dirty ? (
                        <Badge variant="warning">
                            {t('rendition.dirty', 'Outdated')}
                        </Badge>
                    ) : null}
                </div>
                {file ? (
                    <div className="text-xs text-muted-foreground">
                        {file.type} · {formatFileSize(file.size, true, lang)}
                    </div>
                ) : null}
                <div className="flex flex-wrap gap-1 pt-1">
                    {file?.url ? (
                        <Button variant="ghost" size="sm" asChild>
                            <a
                                href={file.url}
                                download
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <DownloadIcon />{' '}
                                {t('asset.actions.download', 'Download')}
                            </a>
                        </Button>
                    ) : null}
                    {file ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                openModal(SaveAsDialog, {asset, file})
                            }
                        >
                            <SaveIcon />{' '}
                            {t('asset.actions.save_as', 'Save as…')}
                        </Button>
                    ) : null}
                    {rendition.definition?.substitutable &&
                    asset.capabilities.edit ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                openModal(UploadRenditionDialog, {
                                    asset,
                                    definition:
                                        rendition.definition as RenditionDefinition,
                                    onUploaded: onChanged,
                                })
                            }
                        >
                            <UploadIcon />{' '}
                            {t('rendition.substitute', 'Substitute')}
                        </Button>
                    ) : null}
                    {asset.capabilities.edit && !rendition.locked ? (
                        <Tooltip content={t('common.delete', 'Delete')}>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-destructive"
                                onClick={() =>
                                    openModal(ConfirmDialog, {
                                        title: t(
                                            'rendition.delete.title',
                                            'Delete rendition "{{name}}"?',
                                            {
                                                name:
                                                    rendition.displayName ??
                                                    rendition.name,
                                            }
                                        ),
                                        destructive: true,
                                        onConfirm: async () => {
                                            await deleteRendition(rendition.id);
                                            toast.success(
                                                t(
                                                    'rendition.deleted',
                                                    'Rendition deleted'
                                                )
                                            );
                                            onChanged();
                                        },
                                    })
                                }
                            >
                                <Trash2Icon />
                            </Button>
                        </Tooltip>
                    ) : null}
                </div>
            </div>
        </div>
    );
}
