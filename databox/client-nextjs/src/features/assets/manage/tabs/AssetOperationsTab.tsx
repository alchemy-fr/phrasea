'use client';

import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {useQueryClient} from '@tanstack/react-query';
import {RotateCcwIcon, Trash2Icon, UnlinkIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {AssetTabProps} from '../AssetManageRoute';
import {Button} from '@/components/ui/button';
import {Alert} from '@/components/ui/misc';
import {CollectionChip, WorkspaceChip} from '@/components/chips';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {
    deleteAssets,
    deleteAssetShortcut,
    restoreAssets,
} from '@/lib/api/assets';
import {useAssetStore} from '@/features/assets/assetStore';

export function AssetOperationsTab({asset, refresh}: AssetTabProps) {
    const {t} = useTranslation();
    const router = useRouter();
    const {openModal} = useModals();
    const queryClient = useQueryClient();
    const removeFromStore = useAssetStore(s => s.remove);
    const shortcuts = (asset.collections ?? []).filter(
        c => c.id !== asset.referenceCollection?.id
    );

    return (
        <div className="max-w-2xl space-y-6">
            <section className="space-y-2">
                <h3 className="text-sm font-semibold">
                    {t('asset.ops.location', 'Location')}
                </h3>
                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <WorkspaceChip workspace={asset.workspace} size="sm" />
                    {asset.referenceCollection ? (
                        <CollectionChip
                            collection={asset.referenceCollection}
                            absolute
                            size="sm"
                        />
                    ) : (
                        <span className="text-muted-foreground">
                            {t('asset.ops.workspace_root', 'Workspace root')}
                        </span>
                    )}
                </div>
            </section>

            <section className="space-y-2">
                <h3 className="text-sm font-semibold">
                    {t('asset.ops.shortcuts', 'Shortcuts')}
                </h3>
                {shortcuts.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'asset.ops.no_shortcut',
                            'This asset has no shortcut in other collections'
                        )}
                    </p>
                ) : null}
                <ul className="space-y-1">
                    {shortcuts.map(c => (
                        <li
                            key={c.id}
                            className="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
                        >
                            <CollectionChip collection={c} absolute size="sm" />
                            <Button
                                variant="ghost"
                                size="sm"
                                className="ml-auto"
                                onClick={() =>
                                    openModal(ConfirmDialog, {
                                        title: t(
                                            'asset.ops.remove_shortcut.title',
                                            'Remove shortcut from "{{name}}"?',
                                            {name: c.displayName ?? c.name}
                                        ),
                                        onConfirm: async () => {
                                            await deleteAssetShortcut(
                                                asset.id,
                                                c.id
                                            );
                                            refresh();
                                        },
                                    })
                                }
                            >
                                <UnlinkIcon />{' '}
                                {t('asset.ops.remove_shortcut', 'Remove')}
                            </Button>
                        </li>
                    ))}
                </ul>
            </section>

            {asset.capabilities.delete ? (
                <Alert
                    variant="destructive"
                    title={t('asset.ops.danger_zone', 'Danger zone')}
                >
                    <div className="mt-2 flex flex-wrap gap-2">
                        {asset.deleted ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    openModal(ConfirmDialog, {
                                        title: t(
                                            'asset.restore.title_single',
                                            'Restore this asset?'
                                        ),
                                        onConfirm: async () => {
                                            await restoreAssets([asset.id]);
                                            toast.success(
                                                t(
                                                    'asset.restore.done',
                                                    '{{count}} asset(s) restored',
                                                    {count: 1}
                                                )
                                            );
                                            refresh();
                                        },
                                    })
                                }
                            >
                                <RotateCcwIcon />{' '}
                                {t('common.restore', 'Restore')}
                            </Button>
                        ) : null}
                        <Button
                            variant="destructive"
                            size="sm"
                            onClick={() =>
                                openModal(ConfirmDialog, {
                                    title: asset.deleted
                                        ? t(
                                              'asset.delete.title_permanent',
                                              'Permanently delete {{count}} asset(s)?',
                                              {count: 1}
                                          )
                                        : t(
                                              'asset.delete.title',
                                              'Delete {{count}} asset(s)?',
                                              {count: 1}
                                          ),
                                    destructive: true,
                                    textToType: asset.name ?? undefined,
                                    onConfirm: async () => {
                                        await deleteAssets([asset.id], {
                                            hardDelete: !!asset.deleted,
                                        });
                                        removeFromStore([asset.id]);
                                        void queryClient.invalidateQueries({
                                            queryKey: ['asset', asset.id],
                                        });
                                        toast.success(
                                            t(
                                                'asset.delete.done',
                                                '{{count}} asset(s) moved to trash',
                                                {count: 1}
                                            )
                                        );
                                        router.back();
                                    },
                                })
                            }
                        >
                            <Trash2Icon />{' '}
                            {asset.deleted
                                ? t(
                                      'asset.actions.delete_permanently',
                                      'Delete permanently'
                                  )
                                : t('common.delete', 'Delete')}
                        </Button>
                    </div>
                </Alert>
            ) : null}
        </div>
    );
}
