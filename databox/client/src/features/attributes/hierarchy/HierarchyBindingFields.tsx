'use client';

import {useEffect} from 'react';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {FormRow, Input} from '@alchemy/next-ui/ui/input';
import {Checkbox, LabeledControl} from '@alchemy/next-ui/ui/controls';
import {getAttributeEntities, getEntityList} from '@/lib/api/metadata';
import {idFromIri} from '@/lib/utils/iri';

export type HierarchyBindingValue = {
    /** Empty: no lower bound */
    depthMin: string;
    /** Empty: no upper bound */
    depthMax: string;
    autoFillParents: boolean;
};

export function isHierarchyBound(value: HierarchyBindingValue): boolean {
    return value.depthMin !== '' || value.depthMax !== '';
}

/** Both bounds set and in the wrong order */
export function isHierarchyRangeInvalid(value: HierarchyBindingValue): boolean {
    return (
        value.depthMin !== '' &&
        value.depthMax !== '' &&
        Number(value.depthMin) > Number(value.depthMax)
    );
}

/** "2", "1–3", "≥ 2" or "≤ 3" */
export function formatDepthRange(
    min: number | null | undefined,
    max: number | null | undefined
): string {
    if (min != null && max != null) {
        return min === max ? String(min) : `${min}–${max}`;
    }

    return min != null ? `≥ ${min}` : max != null ? `≤ ${max}` : '';
}

/**
 * Binds an entity attribute to a range of depths of its list hierarchy: the
 * attribute then only accepts the entities between those levels, and a value
 * may fill the attributes bound to the levels above. Nothing for a list not
 * flagged as a hierarchy, whose binding is cleared.
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
    const flat = !!list.data && !list.data.isHierarchy;
    const bound = isHierarchyBound(value);

    useEffect(() => {
        if (flat && bound) {
            onChange({depthMin: '', depthMax: '', autoFillParents: false});
        }
    }, [flat, bound, onChange]);

    if (!listId || !list.data || flat) {
        return null;
    }

    const set = (patch: Partial<HierarchyBindingValue>) => {
        const next = {...value, ...patch};
        onChange(
            isHierarchyBound(next) ? next : {...next, autoFillParents: false}
        );
    };

    return (
        <>
            <div className="grid grid-cols-2 gap-3">
                <DepthField
                    listId={listId}
                    label={t(
                        'attribute_def.hierarchy.depth_min',
                        'Minimum hierarchy level'
                    )}
                    value={value.depthMin}
                    onChange={depthMin => set({depthMin})}
                />
                <DepthField
                    listId={listId}
                    label={t(
                        'attribute_def.hierarchy.depth_max',
                        'Maximum hierarchy level'
                    )}
                    value={value.depthMax}
                    onChange={depthMax => set({depthMax})}
                />
            </div>
            {isHierarchyRangeInvalid(value) ? (
                <p className="text-sm text-destructive">
                    {t(
                        'attribute_def.hierarchy.depth_order',
                        'The minimum level must not exceed the maximum one'
                    )}
                </p>
            ) : null}
            {isHierarchyBound(value) ? (
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
            ) : null}
        </>
    );
}

/** A bound, with a few entities of its level as help */
function DepthField({
    listId,
    label,
    value,
    onChange,
}: {
    listId: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    const {t} = useTranslation();
    const depth = value === '' ? undefined : Number(value);
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
        enabled: depth !== undefined,
    });

    return (
        <FormRow
            label={label}
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
                          'Empty: no bound (0: the top level). Both empty: any entity of the list'
                      )
            }
        >
            <Input
                type="number"
                min={0}
                value={value}
                onChange={e => onChange(e.target.value)}
            />
        </FormRow>
    );
}
