'use client';

import {useEffect, useMemo} from 'react';
import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {useInfiniteQuery} from '@tanstack/react-query';
import {ExternalLinkIcon} from 'lucide-react';
import type {Asset, AttributeEntity, EntityList} from '@/types/api';
import {AttributeEntityStatus, EntityName} from '@/types/api';
import {searchAssets} from '@/lib/api/assets';
import {getAttributesOfDefinition} from '@/lib/api/metadata';
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
import {assetIdOf} from '@/features/faces/useFaceRecognition';
import {useEntitiesStore} from '@/features/search/entitiesStore';
import {iri} from '@/lib/utils/iri';
import {
    entityAssetsCondition,
    entityAssetsSearchUrl,
    entityListDefinitions,
    entityListSlugs,
} from './entityAssetsSearch';

const PAGE_SIZE = 24;

/**
 * The assets holding a value, and a link to their search. The values not
 * approved yet are left out of the search index: their assets are found
 * through the attributes holding them.
 */
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

    const listDefinitions = useMemo(
        () => entityListDefinitions(definitions, workspaceId, list.id),
        [definitions, workspaceId, list.id]
    );
    const slugs = useMemo(
        () => entityListSlugs(listDefinitions),
        [listDefinitions]
    );
    const definitionIds = useMemo(
        () => listDefinitions.map(d => d.id),
        [listDefinitions]
    );
    const indexed = entity.status === AttributeEntityStatus.Approved;
    const condition = entityAssetsCondition(slugs, entity.id);
    const url = indexed
        ? entityAssetsSearchUrl(slugs, entity.id, workspaceId)
        : undefined;

    const assets = useInfiniteQuery({
        queryKey: ['entity-assets', entity.id, indexed, slugs],
        enabled: !!condition,
        queryFn: async ({pageParam, signal}) => {
            if (indexed) {
                return searchAssets(
                    {
                        url: pageParam,
                        conditions: condition ? [condition.query] : [],
                        workspaces: [workspaceId],
                        limit: PAGE_SIZE,
                    },
                    signal
                );
            }

            const attributes = await getAttributesOfDefinition(
                {
                    url: pageParam,
                    definition: definitionIds,
                    value: entity.id,
                    limit: PAGE_SIZE,
                },
                signal
            );
            const ids = [
                ...new Set(
                    attributes.items
                        .map(assetIdOf)
                        .filter((id): id is string => !!id)
                ),
            ];
            const found =
                ids.length > 0
                    ? (await searchAssets({ids, limit: ids.length}, signal))
                          .items
                    : [];
            const byId = new Map(found.map(a => [a.id, a]));

            return {
                items: ids
                    .map(id => byId.get(id))
                    .filter((a): a is Asset => !!a),
                next: attributes.next,
                // Counts the values, an asset may hold several of them
                total: undefined,
            };
        },
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    const items = uniqueById(assets.data?.pages.flatMap(p => p.items) ?? []);
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

function uniqueById<T extends {id: string}>(items: T[]): T[] {
    const seen = new Set<string>();

    return items.filter(i => !seen.has(i.id) && !!seen.add(i.id));
}
