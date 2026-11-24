'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {MoveIcon} from 'lucide-react';
import type {AttributeEntity, EntityList} from '@/types/api';
import {
    getEntityHierarchyImpact,
    moveAttributeEntity,
} from '@/lib/api/metadata';
import {FormRow} from '@alchemy/next-ui/ui/input';
import {InlineLoader} from '@alchemy/next-ui/ui/loader';
import {AttributeEntitySelect} from '@/components/form/selects';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import type {ModalProps} from '@alchemy/next-ui/modals/ModalProvider';
import {formatDepthRange} from '@/features/attributes/hierarchy/HierarchyBindingFields';

/**
 * Moves an entity (and its branch) under another parent, after showing what
 * the move affects: the "hierarchy" values of the assets holding the branch
 * are recomputed, the values entered by users are kept.
 *
 * A drop gives the parent and the position; no parent moves the branch to
 * the top level.
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
    const [parentId, setParentId] = useState<string | undefined>(
        droppedParentId ?? undefined
    );
    const toTop = !parentId;
    const impact = useQuery({
        queryKey: ['entity-hierarchy-impact', entity.id],
        queryFn: () => getEntityHierarchyImpact(entity.id),
    });

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={t('entity_hierarchy.move_title', 'Move "{{value}}"', {
                value: entity.value,
            })}
            submitLabel={t('entity_hierarchy.move_submit', 'Move')}
            submitIcon={<MoveIcon />}
            canSubmit={
                parentId !== entity.id &&
                (parentId ?? null) !== (entity.parentId ?? null)
            }
            onSubmit={async () => {
                await moveAttributeEntity(
                    entity.id,
                    parentId ?? null,
                    // Another parent than the dropped one: at the end
                    (parentId ?? null) === (droppedParentId ?? null) &&
                        position !== undefined
                        ? position
                        : Number.MAX_SAFE_INTEGER
                );
                await onMoved();
            }}
        >
            <FormRow
                label={t('entity_hierarchy.new_parent', 'New parent')}
                help={
                    toTop
                        ? t(
                              'entity_hierarchy.to_top',
                              'No parent: the entity and its branch move to the top level.'
                          )
                        : undefined
                }
            >
                <AttributeEntitySelect
                    listId={list.id}
                    value={parentId}
                    onChange={setParentId}
                />
            </FormRow>
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
                                        .map(
                                            d =>
                                                `${d.name} (${formatDepthRange(d.depthMin, d.depthMax)})`
                                        )
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
