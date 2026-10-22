'use client';

import {useEffect, useRef, useState} from 'react';
import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {
    ExternalLinkIcon,
    GalleryHorizontalIcon,
    GitMergeIcon,
    MoreHorizontalIcon,
    PencilIcon,
    Trash2Icon,
} from 'lucide-react';
import type {AttributeEntity, EntityList} from '@/types/api';
import {AttributeEntityStatus, EntityName} from '@/types/api';
import {
    deleteAttributeEntity,
    mergeAttributeEntities,
    putAttributeEntity,
} from '@/lib/api/metadata';
import {AttributeEntitySelect} from '@/components/form/selects';
import {EntityChip} from '@/components/chips';
import {Button} from '@/components/ui/button';
import {ConfirmDialog} from '@/components/ui/confirm';
import {Input} from '@/components/ui/input';
import {Badge, Skeleton} from '@/components/ui/misc';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {useModals} from '@/components/modals/ModalProvider';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {useEntitiesStore} from '@/features/search/entitiesStore';
import {EntityAssetsPreviewDialog} from '@/features/workspaces/manage/entities/EntityAssetsPreviewDialog';
import {useEntityAssets} from '@/features/workspaces/manage/entities/useEntityAssets';
import {routes} from '@/lib/routes';
import {iri} from '@/lib/utils/iri';

/** Assets previewed on a card */
const PREVIEW_SIZE = 6;

/** True once the element has come near the viewport (stays true) */
function useSeen<T extends Element>() {
    const ref = useRef<T>(null);
    const [seen, setSeen] = useState(false);

    useEffect(() => {
        const node = ref.current;
        if (seen || !node) {
            return;
        }
        if (typeof IntersectionObserver === 'undefined') {
            setSeen(true);

            return;
        }
        const observer = new IntersectionObserver(
            entries => {
                if (entries.some(e => e.isIntersecting)) {
                    setSeen(true);
                }
            },
            {rootMargin: '200px 0px'}
        );
        observer.observe(node);

        return () => observer.disconnect();
    }, [seen]);

    return [ref, seen] as const;
}

/**
 * A value of an entity list: how many attributes reference it, a few of the
 * assets holding it (loaded once the card is about to be seen), the way to
 * their search; renamed, merged into another value or deleted by the list
 * editors.
 */
export function EntityValueCard({
    entity,
    list,
    workspaceId,
    canEdit,
    onChanged,
}: {
    entity: AttributeEntity;
    list: EntityList;
    workspaceId: string;
    canEdit: boolean;
    onChanged: () => void;
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const [ref, seen] = useSeen<HTMLDivElement>();
    const [mode, setMode] = useState<'view' | 'rename' | 'merge'>('view');
    const {items, total, url, condition, query} = useEntityAssets({
        entity,
        list,
        workspaceId,
        pageSize: PREVIEW_SIZE,
        enabled: seen,
        staleTime: 60_000,
    });
    const pending = entity.status === AttributeEntityStatus.Pending;
    const shown = items.slice(0, PREVIEW_SIZE);
    const more =
        total !== undefined
            ? Math.max(0, total - shown.length)
            : query.hasNextPage
              ? undefined
              : 0;
    const references = entity.usage?.attributes;

    // The API also removes the attributes referencing the value from the assets
    const confirmDelete = () => {
        const referenced = (references ?? 0) > 0;
        openModal(ConfirmDialog, {
            title: t('entity_list.explore.delete_title', {
                defaultValue: 'Delete "{{name}}"?',
                name: entity.value,
            }),
            description: referenced
                ? t('entity_list.explore.delete_references', {
                      count: references,
                      defaultValue_one:
                          'Its {{count}} reference will be removed from the assets.',
                      defaultValue_other:
                          'Its {{count}} references will be removed from the assets.',
                  })
                : undefined,
            textToType: referenced ? entity.value : undefined,
            destructive: true,
            confirmLabel: t('common.delete', 'Delete'),
            onConfirm: async () => {
                await deleteAttributeEntity(entity.id);
                toast.success(t('entity.deleted', 'Value deleted'));
                onChanged();
            },
        });
    };

    return (
        <div
            ref={ref}
            className="flex min-w-0 flex-col gap-2 rounded-md border bg-card p-3"
            data-testid="entity-value-card"
            data-entity={entity.id}
        >
            {mode === 'rename' ? (
                <RenameForm
                    entity={entity}
                    onDone={changed => {
                        setMode('view');
                        if (changed) {
                            onChanged();
                        }
                    }}
                />
            ) : mode === 'merge' ? (
                <MergeForm
                    entity={entity}
                    listId={list.id}
                    onDone={changed => {
                        setMode('view');
                        if (changed) {
                            onChanged();
                        }
                    }}
                />
            ) : (
                <div className="flex min-w-0 items-start gap-2">
                    <div className="min-w-0 flex-1 space-y-1">
                        <div
                            className="truncate text-sm font-medium"
                            data-testid="entity-value-name"
                        >
                            <EntityChip entity={entity} size="sm" />
                        </div>
                        <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                            {pending ? (
                                <Badge
                                    variant="warning"
                                    data-testid="entity-value-pending"
                                >
                                    {t('entity.pending', 'Pending')}
                                </Badge>
                            ) : null}
                            {references !== undefined ? (
                                <span data-testid="entity-value-references">
                                    {t('entity_list.explore.references', {
                                        count: references,
                                        defaultValue_one: '{{count}} reference',
                                        defaultValue_other:
                                            '{{count}} references',
                                    })}
                                </span>
                            ) : null}
                        </div>
                    </div>
                    {canEdit ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    aria-label={t('common.more', 'More')}
                                    data-testid="entity-value-actions"
                                >
                                    <MoreHorizontalIcon />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    onSelect={() => setMode('rename')}
                                    data-testid="entity-value-rename-button"
                                >
                                    <PencilIcon />{' '}
                                    {t('common.rename', 'Rename')}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => setMode('merge')}
                                    data-testid="entity-value-merge-button"
                                >
                                    <GitMergeIcon />{' '}
                                    {t(
                                        'entity_list.explore.merge',
                                        'Merge into…'
                                    )}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={confirmDelete}
                                    data-testid="entity-value-delete-button"
                                >
                                    <Trash2Icon />{' '}
                                    {t('common.delete', 'Delete')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : null}
                </div>
            )}

            <div
                className="grid grid-cols-3 gap-1.5"
                data-testid="entity-value-assets"
            >
                {!condition ? null : !seen || query.isLoading ? (
                    [...Array(PREVIEW_SIZE)].map((_, i) => (
                        <Skeleton key={i} className="aspect-square" />
                    ))
                ) : shown.length === 0 ? (
                    <p className="col-span-3 py-2 text-xs text-muted-foreground">
                        {t('entity.preview.empty', 'No asset uses this value')}
                    </p>
                ) : (
                    shown.map((a, i) => (
                        <Link
                            key={a.id}
                            href={routes.assetView(a.id)}
                            title={a.name}
                            className="relative block aspect-square min-w-0 overflow-hidden rounded-sm"
                            data-testid="entity-value-thumb"
                        >
                            <AssetThumb asset={a} className="size-full" />
                            {i === shown.length - 1 && more !== 0 ? (
                                <span
                                    className="absolute inset-0 flex items-center justify-center bg-black/50 text-xs font-medium text-white"
                                    data-testid="entity-value-more"
                                >
                                    {more === undefined ? '+' : `+${more}`}
                                </span>
                            ) : null}
                        </Link>
                    ))
                )}
            </div>

            <div className="mt-auto flex flex-wrap items-center justify-end gap-1">
                {condition ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7"
                        data-testid="entity-value-see-all"
                        onClick={() =>
                            openModal(EntityAssetsPreviewDialog, {
                                entity,
                                list,
                                workspaceId,
                            })
                        }
                    >
                        <GalleryHorizontalIcon />{' '}
                        {t('entity_list.explore.see_all', 'See all')}
                    </Button>
                ) : null}
                {url ? (
                    <Button asChild variant="outline" size="sm" className="h-7">
                        <Link
                            href={url}
                            data-testid="entity-value-open-search"
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
                            }}
                        >
                            <ExternalLinkIcon />{' '}
                            {t('entity.preview.open_search', 'Open in search')}
                        </Link>
                    </Button>
                ) : null}
            </div>
        </div>
    );
}

function RenameForm({
    entity,
    onDone,
}: {
    entity: AttributeEntity;
    onDone: (changed: boolean) => void;
}) {
    const {t} = useTranslation();
    const [value, setValue] = useState(entity.value ?? '');
    const [note, setNote] = useState(entity.note ?? '');
    const [saving, setSaving] = useState(false);

    const rename = async () => {
        setSaving(true);
        try {
            await putAttributeEntity(entity.id, {
                value: value.trim(),
                note: note.trim() || null,
            });
            toast.success(t('entity.saved', 'Value saved'));
            onDone(true);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <form
            className="flex flex-col gap-2"
            data-testid="entity-value-rename"
            onSubmit={e => {
                e.preventDefault();
                void rename();
            }}
        >
            <Input
                value={value}
                onChange={e => setValue(e.target.value)}
                aria-label={t('entity.value', 'Value')}
                className="h-8 text-sm"
                autoFocus
                data-testid="entity-value-rename-value"
            />
            <Input
                value={note}
                onChange={e => setNote(e.target.value)}
                placeholder={t('entity.note', 'Note')}
                aria-label={t('entity.note', 'Note')}
                className="h-8 text-sm"
                data-testid="entity-value-rename-note"
            />
            <div className="flex justify-end gap-1">
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => onDone(false)}
                >
                    {t('common.cancel', 'Cancel')}
                </Button>
                <Button
                    type="submit"
                    size="sm"
                    loading={saving}
                    disabled={!value.trim()}
                >
                    {t('common.save', 'Save')}
                </Button>
            </div>
        </form>
    );
}

function MergeForm({
    entity,
    listId,
    onDone,
}: {
    entity: AttributeEntity;
    listId: string;
    onDone: (changed: boolean) => void;
}) {
    const {t} = useTranslation();
    const [saving, setSaving] = useState(false);

    const merge = async (targetId: string) => {
        setSaving(true);
        try {
            const target = await mergeAttributeEntities(targetId, [entity.id]);
            toast.success(
                t('entity_list.explore.merged', {
                    defaultValue: 'Merged into {{name}}',
                    name: target.value,
                })
            );
            onDone(true);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="flex flex-col gap-2" data-testid="entity-value-merge">
            <span className="text-sm">
                {t('entity_list.explore.merge_into', {
                    defaultValue: 'Merge {{name}} into…',
                    name: entity.value,
                })}
            </span>
            <AttributeEntitySelect
                listId={listId}
                value={undefined}
                onChange={v => {
                    if (v && v !== entity.id) {
                        void merge(v);
                    }
                }}
                disabled={saving}
            />
            <div className="flex justify-end">
                <Button size="sm" variant="ghost" onClick={() => onDone(false)}>
                    {t('common.cancel', 'Cancel')}
                </Button>
            </div>
        </div>
    );
}
