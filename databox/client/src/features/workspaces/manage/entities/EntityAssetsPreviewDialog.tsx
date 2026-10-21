'use client';

import {useEffect, useMemo} from 'react';
import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {useInfiniteQuery} from '@tanstack/react-query';
import {ExternalLinkIcon} from 'lucide-react';
import type {AttributeEntity, EntityList} from '@/types/api';
import {EntityName} from '@/types/api';
import {searchAssets} from '@/lib/api/assets';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {InlineLoader} from '@/components/ui/loader';
import {EntityChip} from '@/components/chips';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {useDefinitionsStore} from '@/features/attributes/definitionsStore';
import {useEntitiesStore} from '@/features/search/entitiesStore';
import {iri} from '@/lib/utils/iri';
import {
    entityAssetsCondition,
    entityAssetsSearchUrl,
    entityListDefinitions,
    entityListSlugs,
} from './entityAssetsSearch';

const PAGE_SIZE = 24;

/** The assets holding a value, and a link to their search */
export function EntityAssetsPreviewDialog({
    open,
    onOpenChange,
    entity,
    list,
    workspaceId,
}: ModalProps & {
    entity: AttributeEntity;
    list: EntityList;
    workspaceId: string;
}) {
    const {t} = useTranslation();
    const definitions = useDefinitionsStore(s => s.definitions);
    const loadWorkspace = useDefinitionsStore(s => s.loadWorkspace);
    useEffect(() => {
        void loadWorkspace(workspaceId).catch(() => {});
    }, [loadWorkspace, workspaceId]);

    const slugs = useMemo(
        () =>
            entityListSlugs(
                entityListDefinitions(definitions, workspaceId, list.id)
            ),
        [definitions, workspaceId, list.id]
    );
    const condition = entityAssetsCondition(slugs, entity.id);
    const url = entityAssetsSearchUrl(slugs, entity.id, workspaceId);

    const assets = useInfiniteQuery({
        queryKey: ['entity-assets', entity.id, slugs],
        enabled: !!condition,
        queryFn: ({pageParam}) =>
            searchAssets({
                url: pageParam,
                conditions: condition ? [condition.query] : [],
                workspaces: [workspaceId],
                limit: PAGE_SIZE,
            }),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    const items = assets.data?.pages.flatMap(p => p.items) ?? [];
    const total = assets.data?.pages[0]?.total;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="lg" data-testid="entity-preview-dialog">
                <DialogHeader>
                    <DialogTitle className="flex flex-wrap items-center gap-2 pr-6">
                        {t('entity.preview', 'Assets using this value')}
                        <EntityChip entity={entity} size="sm" />
                    </DialogTitle>
                    {total !== undefined ? (
                        <DialogDescription>
                            {t('entity.preview.total', '{{count}} assets', {
                                count: total,
                            })}
                        </DialogDescription>
                    ) : null}
                </DialogHeader>
                <DialogBody className="min-h-40">
                    {!condition ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            {t(
                                'entity.preview.no_definition',
                                'No attribute is bound to this list'
                            )}
                        </p>
                    ) : assets.isLoading ? (
                        <div className="flex justify-center py-6">
                            <InlineLoader />
                        </div>
                    ) : items.length === 0 ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            {t(
                                'entity.preview.empty',
                                'No asset uses this value'
                            )}
                        </p>
                    ) : (
                        <div className="space-y-3">
                            <div className="grid grid-cols-[repeat(auto-fill,minmax(120px,1fr))] gap-3">
                                {items.map(a => (
                                    <div
                                        key={a.id}
                                        className="min-w-0 space-y-1"
                                        data-testid="entity-preview-asset"
                                    >
                                        <AssetThumb asset={a} size={120} />
                                        <div
                                            className="truncate text-xs"
                                            title={a.name}
                                        >
                                            {a.name}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            {assets.hasNextPage ? (
                                <div className="flex justify-center">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        loading={assets.isFetchingNextPage}
                                        onClick={() =>
                                            void assets.fetchNextPage()
                                        }
                                    >
                                        {t('common.load_more', 'Load more')}
                                    </Button>
                                </div>
                            ) : null}
                        </div>
                    )}
                </DialogBody>
                <DialogFooter>
                    {url ? (
                        <Button asChild>
                            <Link
                                href={url}
                                data-testid="entity-preview-open-search"
                                onClick={() => {
                                    // Lets the condition chip name the value without fetching it
                                    useEntitiesStore
                                        .getState()
                                        .store(
                                            iri(
                                                EntityName.AttributeEntity,
                                                entity.id
                                            ),
                                            {...entity}
                                        );
                                    onOpenChange(false);
                                }}
                            >
                                <ExternalLinkIcon />{' '}
                                {t(
                                    'entity.preview.open_search',
                                    'Open in search'
                                )}
                            </Link>
                        </Button>
                    ) : null}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
