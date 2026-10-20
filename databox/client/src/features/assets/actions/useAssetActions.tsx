'use client';

import {ReactNode, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {
    BookPlusIcon,
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
    PinIcon,
    PinOffIcon,
    ScanSearchIcon,
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
import {CreateStoryDialog} from './CreateStoryDialog';
import {useOptionalResults} from '@/features/search/useOptionalResults';
import {useOptionalSelection} from '@/features/assets/list/SelectionProvider';
import {usePinnedStoriesStore} from '@/features/stories/pinnedStoriesStore';
import {EventCategory, track} from '@/lib/analytics/events';
import {useSimilarSearch} from '@/features/search/useSimilarSearch';

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
    /** Pin a story in the sidebar */
    pin?: boolean;
    /** Create a story holding the assets */
    createStory?: boolean;
    /** Search the assets visually similar */
    similar?: boolean;
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
    pin: true,
    createStory: true,
    similar: true,
};

/** Where the actions are offered, sent with their analytics event */
export type ActionSurface = 'menu' | 'toolbar' | 'viewer';

type Options = {
    onOpen?: () => void;
    context?: ActionContext;
    onComplete?: () => void;
    surface?: ActionSurface;
};

/** Analytics event of an asset action run on `count` assets */
export function trackAssetAction(
    id: string,
    surface: ActionSurface,
    count: number
): void {
    // One event for all the alternate URLs (`alt-0`, `alt-1`…)
    track(EventCategory.AssetAction, id.replace(/^alt-\d+$/, 'alt'), {
        name: surface,
        value: count,
    });
}

/**
 * Builds the actions available for one or several assets, grouped for menus.
 *
 * An action is left out only where the action context excludes it, where the
 * user is not authenticated, where the deleted state of the assets rules it
 * out (restore in the trash only), or where no workspace of the assets has
 * the feature (similarity search). It is disabled — listed, greyed out —
 * when it cannot apply to these assets: a single-asset action on several
 * assets, a capability missing on at least one of them, no source file.
 */
export function useAssetActions(
    assets: Asset[],
    {onOpen, context, onComplete, surface = 'menu'}: Options = {}
): AssetAction[][] {
    const {t} = useTranslation();
    const router = useRouter();
    const {openModal} = useModals();
    const {isAuthenticated} = useAuth();
    const addToBasket = useBasketStore(s => s.addToCurrent);
    const openAsset = useAssetOpener();
    const results = useOptionalResults();
    const selection = useOptionalSelection();
    const pinnedStories = usePinnedStoriesStore(s => s.ids);
    const togglePinnedStory = usePinnedStoriesStore(s => s.toggle);
    const searchSimilar = useSimilarSearch();
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

        if (ctx.open) {
            nav.push({
                id: 'open',
                label: t('asset.actions.open', 'Open'),
                icon: <ExpandIcon />,
                disabled: !single,
                run: () => {
                    if (!single) {
                        return;
                    }
                    if (onOpen) {
                        onOpen();
                    } else {
                        openAsset(single);
                    }
                },
            });
        }
        if (ctx.info) {
            nav.push({
                id: 'info',
                label: t('asset.actions.info', 'Info'),
                icon: <InfoIcon />,
                disabled: !single,
                // The tabs of the former manage dialog live in the side
                // panel of the viewer
                run: () => {
                    if (single) {
                        router.push(routes.assetView(single.id));
                    }
                },
            });
        }
        if (
            ctx.similar &&
            !anyDeleted &&
            assets.some(a => a.workspace.similarity)
        ) {
            nav.push({
                id: 'similar',
                label: t('asset.actions.find_similar', 'Find similar'),
                icon: <ScanSearchIcon />,
                disabled: !single?.workspace.similarity,
                run: () => {
                    if (single) {
                        searchSimilar(single);
                    }
                },
            });
        }
        if (
            single?.storyCollection &&
            isAuthenticated &&
            ctx.pin &&
            !anyDeleted
        ) {
            const pinned = pinnedStories.includes(single.id);
            nav.push({
                id: 'pin-story',
                label: pinned
                    ? t('story.unpin', 'Unpin story')
                    : t('story.pin', 'Pin story'),
                icon: pinned ? <PinOffIcon /> : <PinIcon />,
                run: () => togglePinnedStory(single.id),
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
                                '{{count}} items added to basket',
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
        if (ctx.export) {
            nav.push({
                id: 'download',
                bulk: true,
                disabled: !assets.some(a => a.source),
                label: single
                    ? t('asset.actions.download', 'Download')
                    : t('asset.actions.export', 'Export'),
                icon: <DownloadIcon />,
                run: () => openModal(ExportDialog, {assets}),
            });
        }
        if (ctx.saveAs && isAuthenticated) {
            nav.push({
                id: 'save-as',
                label: t('asset.actions.save_as', 'Save as…'),
                icon: <SaveIcon />,
                disabled: !single?.source?.url,
                run: () => {
                    if (single?.source?.url) {
                        openModal(SaveAsDialog, {
                            asset: single,
                            file: single.source,
                        });
                    }
                },
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
            if (ctx.edit) {
                manage.push({
                    id: 'edit',
                    bulk: true,
                    disabled: !can('editAttributes'),
                    label: single
                        ? t('common.edit', 'Edit')
                        : t('asset.actions.edit_attributes', 'Edit attributes'),
                    icon: <PencilIcon />,
                    run: () => {
                        if (single) {
                            router.push(
                                routes.assetView(
                                    single.id,
                                    undefined,
                                    '#panel=edit'
                                )
                            );
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
            if (ctx.share) {
                manage.push({
                    id: 'share',
                    bulk: true,
                    disabled: !can('share'),
                    label: t('asset.actions.share', 'Share'),
                    icon: <ShareIcon />,
                    run: () => {
                        // A share holds the assets of a single workspace
                        if (new Set(assets.map(a => a.workspace.id)).size > 1) {
                            toast.error(
                                t(
                                    'share.mixed_workspaces',
                                    'Assets of different workspaces cannot be shared together'
                                )
                            );

                            return;
                        }
                        openModal(ShareDialog, {assets});
                    },
                });
            }
            if (ctx.move) {
                manage.push({
                    id: 'move',
                    bulk: true,
                    disabled: !can('edit'),
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
            if (ctx.createStory) {
                manage.push({
                    id: 'create-story',
                    bulk: true,
                    label: t('story.create.action', 'Create a story…'),
                    icon: <BookPlusIcon />,
                    run: () => {
                        if (new Set(assets.map(a => a.workspace.id)).size > 1) {
                            toast.error(
                                t(
                                    'story.create.mixed_workspaces',
                                    'Assets of different workspaces cannot be grouped in a story'
                                )
                            );

                            return;
                        }
                        openModal(CreateStoryDialog, {
                            assets,
                            onComplete: complete,
                        });
                    },
                });
            }
            if (ctx.replace) {
                manage.push({
                    id: 'replace',
                    label: t(
                        'asset.actions.replace_source',
                        'Replace source file'
                    ),
                    icon: <RefreshCwIcon />,
                    disabled: !single?.capabilities.edit,
                    run: () => {
                        if (single) {
                            openModal(ReplaceSourceDialog, {
                                asset: single,
                                onComplete: complete,
                            });
                        }
                    },
                });
            }
        }
        if (manage.length > 0) {
            groups.push(manage);
        }

        if (isAuthenticated) {
            if (ctx.restore && allDeleted) {
                danger.push({
                    id: 'restore',
                    bulk: true,
                    disabled: !can('delete'),
                    label: t('asset.actions.restore', 'Restore'),
                    icon: <RotateCcwIcon />,
                    run: () =>
                        openModal(RestoreAssetsDialog, {
                            assets,
                            onComplete: complete,
                        }),
                });
            }
            if (ctx.delete) {
                danger.push({
                    id: 'delete',
                    bulk: true,
                    destructive: true,
                    disabled: !can('delete'),
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

        return groups
            .filter(g => g.length > 0)
            .map(g =>
                g.map(action => ({
                    ...action,
                    run: () => {
                        trackAssetAction(action.id, surface, assets.length);
                        action.run();
                    },
                }))
            );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [assets, isAuthenticated, t, onOpen, ctxKey, pinnedStories, surface]);
}
