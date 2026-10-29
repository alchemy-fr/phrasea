'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {MoveIcon, UndoIcon} from 'lucide-react';
import type {AttributeEntity, EntityList} from '@/types/api';
import {
    getEntityHierarchyImpact,
    moveAttributeEntity,
} from '@/lib/api/metadata';
import {FormRow} from '@/components/ui/input';
import {InlineLoader} from '@/components/ui/loader';
import {AttributeEntitySelect} from '@/components/form/selects';
import {FormDialog} from '@/components/modals/FormDialog';
import type {ModalProps} from '@/components/modals/ModalProvider';

/**
 * Moves an entity (and its branch) under another parent, after showing what
 * the move affects: the "hierarchy" values of the assets holding the branch
 * are recomputed, the values entered by users are kept.
 *
 * A drop gives the parent and the position; a null parent takes the branch
 * out of the hierarchy.
 */
export function MoveEntityDialog({
    open,
    onOpenChange,
    list,
    entity,
    parentId: droppedParentId,
    position,
    onMoved,
}: ModalProps & {
    list: EntityList;
    entity: AttributeEntity;
    parentId?: string | null;
    /** Among the siblings of the dropped parent */
    position?: number;
    onMoved: () => unknown;
}) {
    const {t} = useTranslation();
    const detach = droppedParentId === null;
    const [parentId, setParentId] = useState<string | undefined>(
        droppedParentId ?? undefined
    );
    const impact = useQuery({
        queryKey: ['entity-hierarchy-impact', entity.id],
        queryFn: () => getEntityHierarchyImpact(entity.id),
    });

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={
                detach
                    ? t('entity_hierarchy.detach_title', 'Detach "{{value}}"', {
                          value: entity.value,
                      })
                    : t('entity_hierarchy.move_title', 'Move "{{value}}"', {
                          value: entity.value,
                      })
            }
            submitLabel={
                detach
                    ? t('entity_hierarchy.detach_submit', 'Detach')
                    : t('entity_hierarchy.move_submit', 'Move')
            }
            submitIcon={detach ? <UndoIcon /> : <MoveIcon />}
            canSubmit={detach || (!!parentId && parentId !== entity.id)}
            onSubmit={async () => {
                await moveAttributeEntity(
                    entity.id,
                    detach ? null : parentId!,
                    // Another parent than the dropped one: at the end
                    parentId === droppedParentId && position !== undefined
                        ? position
                        : Number.MAX_SAFE_INTEGER
                );
                await onMoved();
            }}
        >
            {detach ? (
                <p className="text-sm">
                    {t(
                        'entity_hierarchy.detach_description',
                        'The entity and its branch leave the hierarchy: they become unclassified and the hierarchical attributes no longer propose them.'
                    )}
                </p>
            ) : (
                <FormRow label={t('entity_hierarchy.new_parent', 'New parent')}>
                    <AttributeEntitySelect
                        listId={list.id}
                        value={parentId}
                        onChange={setParentId}
                    />
                </FormRow>
            )}
            {impact.data ? (
                <div className="space-y-1 rounded-md border bg-muted/30 p-3 text-sm">
                    <p>
                        {t(
                            'entity_hierarchy.impact.entities',
                            '{{count}} entities move (the branch included).',
                            {count: impact.data.entityCount}
                        )}
                    </p>
                    <p>
                        {t(
                            'entity_hierarchy.impact.attributes',
                            '{{count}} attribute values reference them, {{human}} entered by users.',
                            {
                                count: impact.data.attributeCount,
                                human: impact.data.humanAttributeCount,
                            }
                        )}
                    </p>
                    {impact.data.definitions.length > 0 ? (
                        <p className="text-muted-foreground">
                            {t(
                                'entity_hierarchy.impact.definitions',
                                'Recomputed attributes: {{names}}. Values entered by users are kept; remaining inconsistencies are reported.',
                                {
                                    names: impact.data.definitions
                                        .map(d => `${d.name} (${d.depth})`)
                                        .join(', '),
                                }
                            )}
                        </p>
                    ) : null}
                </div>
            ) : (
                <InlineLoader />
            )}
        </FormDialog>
    );
}
