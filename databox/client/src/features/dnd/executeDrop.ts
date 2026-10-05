import type {QueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import {EntityName} from '@/types/api';
import {iri} from '@/lib/utils/iri';
import {toastError} from '@/lib/utils/errors';
import {addAssetsToCollection, copyAssets, moveAssets} from '@/lib/api/assets';
import {addToBasket} from '@/lib/api/misc';
import {moveCollection} from '@/lib/api/collections';
import {useBasketStore} from '@/features/baskets/basketStore';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {
    type AssetOp,
    type DragPayload,
    type DropOp,
    type DropTarget,
    targetName,
} from './types';

type Translate = (
    key: string,
    defaultValue: string,
    options?: Record<string, unknown>
) => string;

export type DropDeps = {
    t: Translate;
    queryClient: QueryClient;
    /** Reloads the search results, when a list is displayed */
    reloadResults?: () => Promise<void> | void;
    /** Asks the user before a duplication into another workspace */
    confirm: (options: {
        title: string;
        description?: string;
    }) => Promise<boolean>;
};

/**
 * Performs a drop: the API call, the local stores and the toast.
 * Resolves to `false` when nothing was done (cancelled or failed).
 */
export async function executeDrop(
    payload: DragPayload,
    target: DropTarget,
    op: DropOp,
    deps: DropDeps
): Promise<boolean> {
    try {
        if (payload.type === 'collection') {
            return await dropCollection(payload.collection.id, target, deps);
        }
        if (op === 'move-collection') {
            return false;
        }

        return await dropAssets(payload, target, op, deps);
    } catch (e) {
        toastError(e);

        return false;
    }
}

async function dropCollection(
    id: string,
    target: DropTarget,
    {t}: DropDeps
): Promise<boolean> {
    let parentId: string | undefined;
    if (target.type === 'collection') {
        parentId = target.collection.id;
    } else if (target.type !== 'workspace') {
        return false;
    }
    await moveCollection(id, parentId);
    useCollectionStore.getState().moveCollection(id, parentId);
    toast.success(t('collection.move.done', 'Collection moved'));

    return true;
}

async function dropAssets(
    payload: Extract<DragPayload, {type: 'assets'}>,
    target: DropTarget,
    op: AssetOp,
    deps: DropDeps
): Promise<boolean> {
    const {t, queryClient, reloadResults} = deps;
    // The same asset twice when both of its basket items are dragged
    const ids = [...new Set(payload.assets.map(a => a.id))];
    const count = ids.length;
    const name = targetName(target) || t('dnd.untitled', 'untitled');

    switch (target.type) {
        case 'basket': {
            const basket = await addToBasket(target.basket.id, ids);
            useBasketStore.getState().upsert(basket);
            void queryClient.invalidateQueries({
                queryKey: ['basket-assets', basket.id],
            });
            toast.success(
                t('basket.added', '{{count}} items added to basket', {
                    count,
                })
            );

            return true;
        }
        case 'workspace': {
            if (!(await confirmDuplication(payload, target, deps))) {
                return false;
            }
            await copyAssets(
                ids,
                iri(EntityName.Workspace, target.workspace.id),
                false,
                {
                    withAttributes: true,
                    withTags: true,
                }
            );
            toast.success(
                t(
                    'asset.copy.started',
                    'Copying {{count}} assets to {{name}}…',
                    {
                        count,
                        name,
                    }
                )
            );

            return true;
        }
        case 'collection':
        case 'story': {
            const collectionId =
                target.type === 'collection'
                    ? target.collection.id
                    : target.story.storyCollection?.id;
            if (!collectionId) {
                return false;
            }
            const destination = iri(EntityName.Collection, collectionId);

            if (op === 'add') {
                // A story is addressed by its asset: the API resolves its collection
                await addAssetsToCollection(
                    ids,
                    target.type === 'story'
                        ? iri(EntityName.Asset, target.story.id)
                        : destination
                );
                toast.success(
                    target.type === 'story'
                        ? t(
                              'story.assets_added',
                              '{{count}} assets added to story {{name}}',
                              {count, name}
                          )
                        : t(
                              'collection.assets_added',
                              '{{count}} assets added to {{name}}',
                              {count, name}
                          )
                );
            } else if (op === 'move') {
                await moveAssets(ids, destination);
                toast.success(
                    t(
                        'asset.move.started',
                        'Moving {{count}} assets to {{name}}…',
                        {
                            count,
                            name,
                        }
                    )
                );
            } else {
                if (!(await confirmDuplication(payload, target, deps))) {
                    return false;
                }
                await copyAssets(ids, destination, false, {
                    withAttributes: true,
                    withTags: true,
                });
                toast.success(
                    t(
                        'asset.copy.started',
                        'Copying {{count}} assets to {{name}}…',
                        {
                            count,
                            name,
                        }
                    )
                );
            }
            if (target.type === 'story') {
                void queryClient.invalidateQueries({
                    queryKey: ['story-assets', target.story.id],
                });
                void queryClient.invalidateQueries({
                    queryKey: ['story-thumbnails', target.story.id],
                });
            }
            await reloadResults?.();

            return true;
        }
        default:
            return false;
    }
}

/**
 * Duplicating into another workspace creates new files: it is confirmed
 * when the duplication was not asked for explicitly (Ctrl held).
 */
async function confirmDuplication(
    payload: Extract<DragPayload, {type: 'assets'}>,
    target: DropTarget,
    {t, confirm}: DropDeps
): Promise<boolean> {
    const workspaceId =
        target.type === 'workspace'
            ? target.workspace.id
            : target.type === 'collection'
              ? (target.collection.workspaceId ??
                target.collection.workspace?.id)
              : undefined;
    const crossWorkspace = payload.assets.some(
        a => a.workspace?.id !== workspaceId
    );
    if (!crossWorkspace) {
        return true;
    }

    return confirm({
        title: t(
            'asset.copy.confirm_workspace',
            'Duplicate {{count}} assets into "{{name}}"?',
            {
                count: payload.assets.length,
                name: targetName(target) || t('dnd.untitled', 'untitled'),
            }
        ),
        description: t(
            'asset.copy.cross_workspace',
            'Assets cannot be linked across workspaces: they will be duplicated.'
        ),
    });
}
