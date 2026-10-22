'use client';

import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {ExternalLinkIcon} from 'lucide-react';
import type {AttributeEntity, EntityList} from '@/types/api';
import {EntityName} from '@/types/api';
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
import {useEntitiesStore} from '@/features/search/entitiesStore';
import {iri} from '@/lib/utils/iri';
import {useEntityAssets} from './useEntityAssets';

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
    const {
        items,
        total,
        url,
        condition,
        query: assets,
    } = useEntityAssets({entity, list, workspaceId, pageSize: PAGE_SIZE});

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
