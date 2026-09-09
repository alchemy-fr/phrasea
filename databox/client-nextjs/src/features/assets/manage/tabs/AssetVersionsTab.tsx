'use client';

import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {DownloadIcon, SaveIcon, Trash2Icon} from 'lucide-react';
import {toast} from 'sonner';
import type {AssetTabProps} from '../AssetManageRoute';
import {deleteAssetFileVersion, getAssetFileVersions} from '@/lib/api/assets';
import {Button} from '@/components/ui/button';
import {Skeleton} from '@/components/ui/misc';
import {FilePlayer} from '@/features/assets/player/FilePlayer';
import {formatDateTime, formatFileSize} from '@/lib/utils/format';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {SaveAsDialog} from '@/features/assets/actions/SaveAsDialog';

export function AssetVersionsTab({asset}: AssetTabProps) {
    const {t, i18n} = useTranslation();
    const {openModal} = useModals();
    const queryClient = useQueryClient();
    const queryKey = ['asset-versions', asset.id];
    const versions = useQuery({
        queryKey,
        queryFn: () => getAssetFileVersions(asset.id),
    });

    if (versions.isLoading) {
        return <Skeleton className="h-24" />;
    }
    const items = versions.data?.items ?? [];
    if (items.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t(
                    'asset.versions.empty',
                    'No previous version of the source file'
                )}
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {items.map(v => (
                <div key={v.id} className="flex gap-3 rounded-md border p-3">
                    <div className="flex size-28 shrink-0 items-center justify-center overflow-hidden rounded bg-media-bg">
                        <FilePlayer file={v.file} controls={false} />
                    </div>
                    <div className="min-w-0 flex-1 space-y-1 text-sm">
                        <div className="font-medium">{v.name}</div>
                        <div className="text-xs text-muted-foreground">
                            {formatDateTime(
                                v.createdAt,
                                'medium',
                                i18n.language
                            )}{' '}
                            · {v.file.type} ·{' '}
                            {formatFileSize(v.file.size, true, i18n.language)}
                        </div>
                        <div className="flex flex-wrap gap-1 pt-1">
                            {v.file.url ? (
                                <Button variant="ghost" size="sm" asChild>
                                    <a
                                        href={v.file.url}
                                        download
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <DownloadIcon />{' '}
                                        {t(
                                            'asset.actions.download',
                                            'Download'
                                        )}
                                    </a>
                                </Button>
                            ) : null}
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    openModal(SaveAsDialog, {
                                        asset,
                                        file: v.file,
                                    })
                                }
                            >
                                <SaveIcon />{' '}
                                {t('asset.actions.save_as', 'Save as…')}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-destructive"
                                onClick={() =>
                                    openModal(ConfirmDialog, {
                                        title: t(
                                            'asset.versions.delete.title',
                                            'Delete version "{{name}}"?',
                                            {name: v.name}
                                        ),
                                        destructive: true,
                                        onConfirm: async () => {
                                            await deleteAssetFileVersion(v.id);
                                            toast.success(
                                                t(
                                                    'asset.versions.deleted',
                                                    'Version deleted'
                                                )
                                            );
                                            void queryClient.invalidateQueries({
                                                queryKey,
                                            });
                                        },
                                    })
                                }
                            >
                                <Trash2Icon /> {t('common.delete', 'Delete')}
                            </Button>
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}
