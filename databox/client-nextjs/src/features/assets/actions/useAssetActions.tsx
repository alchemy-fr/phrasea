'use client';

import {ReactNode, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {
    CopyIcon,
    DownloadIcon,
    ExternalLinkIcon,
    FolderInputIcon,
    InfoIcon,
    PencilIcon,
    RefreshCwIcon,
    RotateCcwIcon,
    ShareIcon,
    ShoppingBasketIcon,
    Trash2Icon,
    ExpandIcon,
    SaveIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset} from '@/types/api';
import {useModals} from '@/components/modals/ModalProvider';
import {useAuth} from '@/lib/auth/AuthProvider';
import {routes} from '@/lib/routes';
import {useBasketStore} from '@/features/baskets/basketStore';
import {useAssetOpener} from '@/features/assets/useAssetOpener';
import {ExportDialog} from './ExportDialog';
import {CopyMoveDialog} from './CopyMoveDialog';
import {DeleteAssetsDialog} from './DeleteAssetsDialog';
import {RestoreAssetsDialog} from './RestoreAssetsDialog';
import {ShareDialog} from '@/features/share/ShareDialog';
import {ReplaceSourceDialog} from './ReplaceSourceDialog';
import {SaveAsDialog} from './SaveAsDialog';
import {useOptionalResults} from '@/features/search/useOptionalResults';
import {useOptionalSelection} from '@/features/assets/list/SelectionProvider';

export type AssetAction = {
    id: string;
    label: ReactNode;
    icon?: ReactNode;
    run: () => void;
    disabled?: boolean;
    destructive?: boolean;
    href?: string;
    /** Show in the bulk toolbar */
    bulk?: boolean;
};

export type ActionContext = {
    basket?: boolean;
    export?: boolean;
    edit?: boolean;
    share?: boolean;
    delete?: boolean;
    restore?: boolean;
    open?: boolean;
    move?: boolean;
    copy?: boolean;
    replace?: boolean;
    info?: boolean;
    saveAs?: boolean;
};

export const defaultActionContext: Required<ActionContext> = {
    basket: true,
    export: true,
    edit: true,
    share: true,
    delete: true,
    restore: true,
    open: true,
    move: true,
    copy: true,
    replace: true,
    info: true,
    saveAs: true,
};

type Options = {
    onOpen?: () => void;
    context?: ActionContext;
    onComplete?: () => void;
};

/**
 * Builds the actions available for one or several assets, grouped for menus.
 * Availability follows asset capabilities and the action context of the list.
 */
export function useAssetActions(
    assets: Asset[],
    {onOpen, context, onComplete}: Options = {}
): AssetAction[][] {
    const {t} = useTranslation();
    const router = useRouter();
    const {openModal} = useModals();
    const {isAuthenticated} = useAuth();
    const addToBasket = useBasketStore(s => s.addToCurrent);
    const openAsset = useAssetOpener();
    const results = useOptionalResults();
    const selection = useOptionalSelection();
    const ctx = {...defaultActionContext, ...context};
    const ctxKey = JSON.stringify(ctx);

    return useMemo(() => {
        if (assets.length === 0) {
            return [];
        }
        const single = assets.length === 1 ? assets[0] : undefined;
        const ids = assets.map(a => a.id);
        const complete = () => {
            onComplete?.();
            selection?.clear();
            void results?.reload();
        };
        const anyDeleted = assets.some(a => a.deleted);
        const allDeleted = assets.every(a => a.deleted);
        const can = (cap: keyof Asset['capabilities']) =>
            assets.every(a => a.capabilities[cap]);

        const groups: AssetAction[][] = [];
        const nav: AssetAction[] = [];
        const manage: AssetAction[] = [];
        const danger: AssetAction[] = [];

        if (single && ctx.open) {
            nav.push({
                id: 'open',
                label: t('asset.actions.open', 'Open'),
                icon: <ExpandIcon />,
                run: () => (onOpen ? onOpen() : openAsset(single)),
            });
        }
        if (single && ctx.info) {
            nav.push({
                id: 'info',
                label: t('asset.actions.info', 'Info'),
                icon: <InfoIcon />,
                run: () => router.push(routes.assetManage(single.id, 'info')),
            });
        }
        if (isAuthenticated && ctx.basket && !anyDeleted) {
            nav.push({
                id: 'basket',
                bulk: true,
                label: t('asset.actions.add_to_basket', 'Add to basket'),
                icon: <ShoppingBasketIcon />,
                run: async () => {
                    try {
                        await addToBasket(ids);
                        toast.success(
                            t(
                                'basket.added',
                                '{{count}} item(s) added to basket',
                                {count: ids.length}
                            )
                        );
                        selection?.clear();
                    } catch (e: any) {
                        toast.error(e?.message);
                    }
                },
            });
        }
        if (ctx.export && assets.some(a => a.source)) {
            nav.push({
                id: 'download',
                bulk: true,
                label: single
                    ? t('asset.actions.download', 'Download')
                    : t('asset.actions.export', 'Export'),
                icon: <DownloadIcon />,
                run: () => openModal(ExportDialog, {assets}),
            });
        }
        if (single && ctx.saveAs && single.source?.url && isAuthenticated) {
            nav.push({
                id: 'save-as',
                label: t('asset.actions.save_as', 'Save as…'),
                icon: <SaveIcon />,
                run: () =>
                    openModal(SaveAsDialog, {
                        asset: single,
                        file: single.source!,
                    }),
            });
        }
        if (single?.source?.alternateUrls?.length) {
            single.source.alternateUrls.forEach((alt, i) =>
                nav.push({
                    id: `alt-${i}`,
                    label: alt.label ?? alt.type,
                    icon: <ExternalLinkIcon />,
                    href: alt.url,
                    run: () => window.open(alt.url, '_blank'),
                })
            );
        }
        groups.push(nav);

        if (isAuthenticated && !anyDeleted) {
            if (ctx.edit && can('editAttributes')) {
                manage.push({
                    id: 'edit',
                    bulk: true,
                    label: single
                        ? t('common.edit', 'Edit')
                        : t('asset.actions.edit_attributes', 'Edit attributes'),
                    icon: <PencilIcon />,
                    run: () => {
                        if (single) {
                            router.push(routes.assetManage(single.id, 'edit'));
                        } else {
                            const workspaces = new Set(
                                assets.map(a => a.workspace.id)
                            );
                            if (workspaces.size > 1) {
                                toast.error(
                                    t(
                                        'asset.actions.edit_mixed_workspaces',
                                        'Selected assets must belong to the same workspace'
                                    )
                                );

                                return;
                            }
                            sessionStorage.setItem(
                                'dbx.batch-edit',
                                JSON.stringify(ids)
                            );
                            router.push(routes.attributesEditor());
                        }
                    },
                });
            }
            if (single && ctx.share && single.capabilities.share) {
                manage.push({
                    id: 'share',
                    bulk: true,
                    label: t('asset.actions.share', 'Share'),
                    icon: <ShareIcon />,
                    run: () => openModal(ShareDialog, {asset: single}),
                });
            }
            if (ctx.move && can('edit')) {
                manage.push({
                    id: 'move',
                    bulk: true,
                    label: t('asset.actions.move', 'Move'),
                    icon: <FolderInputIcon />,
                    run: () =>
                        openModal(CopyMoveDialog, {
                            assets,
                            mode: 'move',
                            onComplete: complete,
                        }),
                });
            }
            if (ctx.copy) {
                manage.push({
                    id: 'copy',
                    bulk: true,
                    label: t('asset.actions.copy', 'Copy'),
                    icon: <CopyIcon />,
                    run: () =>
                        openModal(CopyMoveDialog, {
                            assets,
                            mode: 'copy',
                            onComplete: complete,
                        }),
                });
            }
            if (single && ctx.replace && single.capabilities.edit) {
                manage.push({
                    id: 'replace',
                    label: t(
                        'asset.actions.replace_source',
                        'Replace source file'
                    ),
                    icon: <RefreshCwIcon />,
                    run: () =>
                        openModal(ReplaceSourceDialog, {
                            asset: single,
                            onComplete: complete,
                        }),
                });
            }
        }
        if (manage.length > 0) {
            groups.push(manage);
        }

        if (isAuthenticated) {
            if (ctx.restore && allDeleted && can('delete')) {
                danger.push({
                    id: 'restore',
                    bulk: true,
                    label: t('asset.actions.restore', 'Restore'),
                    icon: <RotateCcwIcon />,
                    run: () =>
                        openModal(RestoreAssetsDialog, {
                            assets,
                            onComplete: complete,
                        }),
                });
            }
            if (ctx.delete && can('delete')) {
                danger.push({
                    id: 'delete',
                    bulk: true,
                    destructive: true,
                    label: allDeleted
                        ? t(
                              'asset.actions.delete_permanently',
                              'Delete permanently'
                          )
                        : t('common.delete', 'Delete'),
                    icon: <Trash2Icon />,
                    run: () =>
                        openModal(DeleteAssetsDialog, {
                            assets,
                            hardDelete: allDeleted,
                            onComplete: complete,
                        }),
                });
            }
        }
        if (danger.length > 0) {
            groups.push(danger);
        }

        return groups.filter(g => g.length > 0);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [assets, isAuthenticated, t, onOpen, ctxKey]);
}
