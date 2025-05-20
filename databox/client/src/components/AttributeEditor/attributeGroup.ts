import {Asset, AttributeDefinition, StateSetter, Tag} from '../../types.ts';
import React from 'react';
import {
    AttributeDefinitionIndex,
    AttributesCommit,
    AttributesHistory,
    BatchAttributeIndex,
    DefinitionValuesIndex,
    ExtraAttributeDefinition,
    LocalizedAttributeIndex,
    SetAttributeValueOptions,
    CreateToKeyFunc,
    Values,
} from './types';
import {NO_LOCALE} from '../Media/Asset/Attribute/AttributesEditor';
import {computeValues} from './store/values.ts';
import {
    computeAllDefinitionsValues,
    computeDefinitionValuesHandler,
} from './store/definitionValues.ts';
import {getBatchActions} from './batchActions.ts';
import {useModals} from '@alchemy/navigation';
import SavePreviewDialog from './SavePreviewDialog.tsx';
import {useDirtyFormPrompt} from '../Dialog/Tabbed/FormTab.tsx';
import {useTranslation} from 'react-i18next';
import {AttributeType} from '../../api/attributes.ts';
import {getAttributeType} from '../Media/Asset/Attribute/types';

type Props = {
    attributeDefinitions: AttributeDefinition[];
    assets: Asset[];
    subSelection: Asset[];
    setSubSelection: StateSetter<Asset[]>;
    definition: AttributeDefinition | undefined;
    setDefinition: StateSetter<AttributeDefinition | undefined>;
    onSaved: () => void;
    modalIndex?: number | undefined;
};

export function useAttributeValues<T>({
    attributeDefinitions,
    assets,
    subSelection,
    setSubSelection,
    definition,
    setDefinition,
    onSaved,
    modalIndex,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const [inc, setInc] = React.useState(0);
    const [definitionIndex, setDefinitionIndex] =
        React.useState<AttributeDefinitionIndex>({});

    const createToKey = React.useCallback<CreateToKeyFunc<any>>((fieldType: AttributeType) => {
        const type = getAttributeType(fieldType);

        return (v: any) => {
            if (!v) {
                return '';
            }

            return type.normalize(v)?.toString();
        };
    }, []);

    const tagDefinition = React.useMemo<AttributeDefinition>(
        () =>
            ({
                id: ExtraAttributeDefinition.Tags,
                fieldType: AttributeType.Tag,
                name: t('tags.label', 'Tags'),
                multiple: true,
                canEdit: true,
                translatable: false,
            }) as AttributeDefinition,
        []
    );

    const {initialIndex, finalAttributeDefinitions} = React.useMemo(() => {
        const index: BatchAttributeIndex<T> = {};
        const definitionIndex: AttributeDefinitionIndex = {};

        const finalAttributeDefinitions = [
            tagDefinition,
            ...attributeDefinitions,
        ];

        attributeDefinitions.forEach(def => {
            index[def.id] ??= {};
            definitionIndex[def.id] = def;
        });
        index[ExtraAttributeDefinition.Tags] ??= {};
        definitionIndex[ExtraAttributeDefinition.Tags] = tagDefinition;

        assets.forEach(a => {
            a.attributes.forEach(attribute => {
                const definitionId = attribute.definition.id;

                // Can be undefined due to pagination
                if (!definitionIndex[definitionId]) {
                    attributeDefinitions.push(attribute.definition);
                    definitionIndex[definitionId] = attribute.definition;
                    index[definitionId] = {};
                }

                index[definitionId][a.id] ??= {};
                const definition = definitionIndex[definitionId];
                const assetIndex = index[definitionId][a.id];
                const locale = attribute.locale ?? NO_LOCALE;

                if (definition.multiple) {
                    (assetIndex[locale] as T[]) ??= [];
                    (assetIndex[locale] as T[]).push(attribute.value);
                } else {
                    assetIndex[locale] = attribute.value;
                }
            });

            // Add tags
            (index[ExtraAttributeDefinition.Tags][
                a.id
            ] as LocalizedAttributeIndex<Tag[]>) ??= {
                [NO_LOCALE]: [] as Tag[],
            };
            const tagList = index[ExtraAttributeDefinition.Tags][a.id][
                NO_LOCALE
            ] as Tag[];
            a.tags?.forEach(t => {
                tagList.push(t);
            });
        });

        setDefinitionIndex(definitionIndex);

        return {
            initialIndex: index,
            finalAttributeDefinitions,
        };
    }, [attributeDefinitions, assets]);

    const [history, setHistory] = React.useState<AttributesHistory<T>>({
        history: [
            {
                index: initialIndex,
                definition,
                subSelection,
            },
        ],
        current: 0,
    });

    React.useEffect(() => {
        // Update definition in current history
        setHistory(p => {
            if (
                p.current === p.history.length - 1 &&
                (p.history[p.current].definition !== definition ||
                    p.history[p.current].subSelection !== subSelection)
            ) {
                const h = [...p.history];

                h[p.current] = {
                    ...h[p.current],
                    subSelection,
                    definition,
                };

                return {
                    ...p,
                    history: h,
                };
            }

            return p;
        });
    }, [definition, subSelection]);

    const [index, setIndex] =
        React.useState<BatchAttributeIndex<T>>(initialIndex);

    const initialDefinitionValues = React.useMemo<
        DefinitionValuesIndex<T>
    >(() => {
        return computeAllDefinitionsValues(
            finalAttributeDefinitions,
            subSelection,
            createToKey,
            index
        );
    }, [initialIndex, subSelection]);

    React.useEffect(() => {
        setDefinitionValues(initialDefinitionValues);
    }, [subSelection]);

    const [definitionValues, setDefinitionValues] = React.useState<
        DefinitionValuesIndex<T>
    >(initialDefinitionValues);

    const values = React.useMemo<Values<T> | undefined>(() => {
        if (definition && subSelection.length) {
            return computeValues<T>(
                definition,
                subSelection,
                index,
                initialIndex,
                createToKey
            );
        }
    }, [definition, index, subSelection]);

    const reset = React.useCallback(() => {
        setIndex(initialIndex);
    }, [initialIndex]);

    React.useEffect(() => {
        reset();
    }, [reset]);

    const postUpdate = React.useCallback(
        (np: BatchAttributeIndex<T>) => {
            setHistory(ph => ({
                history: ph.history.slice(0, ph.current + 1).concat([
                    {
                        index: np,
                        subSelection,
                        definition,
                    },
                ]),
                current: ph.current + 1,
            }));

            const values = computeValues<T>(
                definition!,
                subSelection,
                np,
                initialIndex,
                createToKey
            );
            setDefinitionValues(
                computeDefinitionValuesHandler<T>(definition!, values)
            );
        },
        [definition, subSelection, initialIndex]
    );

    const setValue = React.useCallback(
        (
            locale: string,
            value: T | undefined,
            {add, remove, updateInput}: SetAttributeValueOptions = {}
        ) => {
            const defId = definition!.id;
            const attributeDefinition = finalAttributeDefinitions.find(
                ad => ad.id === defId
            )!;

            const toKey = createToKey(attributeDefinition.fieldType);
            const key = value ? toKey(value) : '';

            setIndex(p => {
                const np = {...p};
                const na = {...p[defId]};

                subSelection.forEach(a => {
                    const c = {...(na[a.id] ?? {})};

                    if (add) {
                        if (value) {
                            (c[locale] as T[]) = [
                                ...((c[locale] ?? []) as T[]),
                            ];
                            if (
                                !(c[locale] as T[]).some(i => key === toKey(i))
                            ) {
                                (c[locale] as T[]).push(value);
                            }
                        }
                    } else if (remove) {
                        (c[locale] as T[]) = [...((c[locale] ?? []) as T[])];
                        (c[locale] as T[]) = (c[locale] as T[]).filter(
                            i => key !== toKey(i)
                        );
                    } else {
                        c[locale] = value;
                    }

                    na[a.id] = c;
                });

                np[defId] = na;

                postUpdate(np);

                return np;
            });

            if (updateInput) {
                setInc(p => p + 1);
            }
        },
        [definition, subSelection, postUpdate]
    );

    const toggleValue = React.useCallback(
        (assetId: string, locale: string, value: T, checked: boolean) => {
            const defId = definition!.id;
            const attributeDefinition = finalAttributeDefinitions.find(
                ad => ad.id === defId
            )!;
            locale = attributeDefinition.translatable ? locale : NO_LOCALE;

            const toKey = createToKey(attributeDefinition.fieldType);
            const key = value ? toKey(value) : '';

            setIndex(p => {
                const np = {...p};
                const na = {...p[defId]};
                const c = {...(na[assetId] ?? {})};

                if (checked) {
                    (c[locale] as T[]) = [...((c[locale] ?? []) as T[])];
                    if (!(c[locale] as T[]).some(i => key === toKey(i))) {
                        (c[locale] as T[]).push(value);
                    }
                } else {
                    (c[locale] as T[]) = [...((c[locale] ?? []) as T[])];
                    (c[locale] as T[]) = (c[locale] as T[]).filter(
                        i => key !== toKey(i)
                    );
                }

                na[assetId] = c;
                np[defId] = na;

                postUpdate(np);

                return np;
            });
        },
        [definition, postUpdate]
    );

    const hasValue = React.useCallback(
        (asset: Asset, locale: string, key: string): boolean => {
            if (definition && definition.multiple) {
                locale = definition.translatable ? locale : NO_LOCALE;
                const v = index[definition.id]?.[asset.id]?.[locale];
                const toKey = createToKey(definition.fieldType);
                if (v) {
                    return (v as T[]).some(iv => toKey(iv) === key);
                }
            }

            return false;
        },
        [index, definition]
    );

    const applyHistory = React.useCallback(
        (commit: AttributesCommit<T>) => {
            const newIndex = commit.index;
            setIndex(newIndex);

            const subSelection = commit.subSelection;
            const definition = commit.definition;

            setSubSelection(subSelection);
            setDefinition(definition);
            setDefinitionValues(
                computeAllDefinitionsValues<T>(
                    finalAttributeDefinitions,
                    subSelection,
                    createToKey,
                    newIndex
                )
            );
            setInc(p => p + 1);
            if (definition) {
                const values = computeValues<T>(
                    definition!,
                    subSelection,
                    newIndex,
                    initialIndex,
                    createToKey
                );
                setDefinitionValues(
                    computeDefinitionValuesHandler<T>(definition!, values)
                );
            }
        },
        [definition, subSelection, finalAttributeDefinitions]
    );

    const undo = React.useCallback(() => {
        setHistory(ph => {
            const i = ph.current - 1;
            applyHistory(ph.history[i]);

            return {
                ...ph,
                current: i,
            };
        });
    }, [applyHistory]);

    const redo = React.useCallback(() => {
        setHistory(ph => {
            const i = ph.current + 1;
            applyHistory(ph.history[i]);

            return {
                ...ph,
                current: i,
            };
        });
    }, [applyHistory]);

    const onSave = React.useCallback<() => Promise<void>>(async () => {
        const actions = getBatchActions<T>(
            assets,
            initialIndex,
            index,
            definitionIndex,
            createToKey
        );

        openModal(SavePreviewDialog, {
            actions,
            definitionIndex,
            workspaceId: assets[0].workspace.id,
            onSaved,
        });
    }, [index, initialIndex, definitionIndex, onSaved]);

    useDirtyFormPrompt(history.current > 0, modalIndex);

    return {
        attributeDefinitions: finalAttributeDefinitions,
        inputValueInc: inc,
        values,
        setValue,
        hasValue,
        toggleValue,
        reset,
        index,
        definitionValues,
        history,
        undo: history.current > 0 ? undo : undefined,
        redo: history.current < history.history.length - 1 ? redo : undefined,
        onSave,
        createToKey,
    };
}
