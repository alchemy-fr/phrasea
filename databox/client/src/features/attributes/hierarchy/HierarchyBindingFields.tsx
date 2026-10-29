'use client';

import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {FormRow, Input} from '@/components/ui/input';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {AttributeEntitySelect} from '@/components/form/selects';
import {getAttributeEntities, getEntityList} from '@/lib/api/metadata';
import {idFromIri} from '@/lib/utils/iri';

export type HierarchyBindingValue = {
    /** Empty: not bound to the hierarchy */
    depth: string;
    branchId: string;
    autoFillParents: boolean;
};

/**
 * Binds an entity attribute to a depth of its list hierarchy: the attribute
 * then only accepts the entities of that level (same concept), and a value may
 * fill the attributes bound to the levels above.
 */
export function HierarchyBindingFields({
    entityList,
    value,
    onChange,
}: {
    /** IRI of the entity list */
    entityList: string;
    value: HierarchyBindingValue;
    onChange: (value: HierarchyBindingValue) => void;
}) {
    const {t} = useTranslation();
    const listId = entityList ? idFromIri(entityList) : undefined;
    const list = useQuery({
        queryKey: ['entity-list', listId],
        queryFn: () => getEntityList(listId!),
        enabled: !!listId,
    });
    const depth = value.depth === '' ? undefined : Number(value.depth);
    const samples = useQuery({
        queryKey: ['attribute-entities', 'level-samples', listId, depth],
        queryFn: () =>
            getAttributeEntities({
                list: listId,
                depth,
                placeholder: false,
                limit: 4,
                order: {position: 'asc'},
            }),
        enabled: !!listId && depth !== undefined,
    });

    if (!listId || !list.data?.rootEntityId) {
        return list.data ? (
            <p className="text-xs text-muted-foreground sm:col-span-2">
                {t(
                    'attribute_def.hierarchy.no_root',
                    'The entity list has no hierarchy: set its root entity to bind this attribute to a level.'
                )}
            </p>
        ) : null;
    }

    const set = (patch: Partial<HierarchyBindingValue>) =>
        onChange({...value, ...patch});

    return (
        <>
            <FormRow
                label={t('attribute_def.hierarchy.depth', 'Hierarchy level')}
                help={
                    depth !== undefined
                        ? samples.data && samples.data.items.length > 0
                            ? t('attribute_def.hierarchy.depth_samples', {
                                  defaultValue: 'e.g. {{samples}}',
                                  samples: samples.data.items
                                      .map(e => e.value)
                                      .join(', '),
                              })
                            : t(
                                  'attribute_def.hierarchy.depth_empty',
                                  'No entity at this level yet'
                              )
                        : t(
                              'attribute_def.hierarchy.depth_help',
                              'Empty: any entity of the list (0 is the root)'
                          )
                }
            >
                <Input
                    type="number"
                    min={0}
                    value={value.depth}
                    onChange={e =>
                        set({
                            depth: e.target.value,
                            ...(e.target.value === ''
                                ? {autoFillParents: false, branchId: ''}
                                : {}),
                        })
                    }
                />
            </FormRow>
            {depth !== undefined ? (
                <>
                    <FormRow
                        label={t(
                            'attribute_def.hierarchy.branch',
                            'Restrict to the branch'
                        )}
                    >
                        <AttributeEntitySelect
                            listId={listId}
                            value={value.branchId || undefined}
                            onChange={v => set({branchId: v ?? ''})}
                        />
                    </FormRow>
                    <LabeledControl
                        label={t(
                            'attribute_def.hierarchy.auto_fill',
                            'Fill the attributes of the levels above'
                        )}
                    >
                        <Checkbox
                            checked={value.autoFillParents}
                            onCheckedChange={v =>
                                set({autoFillParents: v === true})
                            }
                        />
                    </LabeledControl>
                </>
            ) : null}
        </>
    );
}
