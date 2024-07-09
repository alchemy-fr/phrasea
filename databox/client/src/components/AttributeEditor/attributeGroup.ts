import {Asset, AttributeDefinition, StateSetter} from '../../types.ts';
import React from 'react';
import {
    AttributeDefinitionIndex,
    BatchAttributeIndex,
    AttributesCommit,
    AttributesHistory,
    DefinitionValuesIndex,
    SetAttributeValueOptions,
    ToKeyFunc,
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
import {useDirtyFormPromptOutsideRouter} from '../Dialog/Tabbed/FormTab.tsx';

type Props<T> = {
    attributeDefinitions: AttributeDefinition[];
    assets: Asset[];
    subSelection: Asset[];
    setSubSelection: StateSetter<Asset[]>;
    toKey: ToKeyFunc<T>;
    definition: AttributeDefinition | undefined;
    setDefinition: StateSetter<AttributeDefinition | undefined>;
    onSaved: () => void;
};

export function useAttributeValues<T>({
    attributeDefinitions,
    assets,
    subSelection,
    setSubSelection,
    toKey,
    definition,
    setDefinition,
    onSaved,
}: Props<T>) {
    const {openModal} = useModals();
    const [inc, setInc] = React.useState(0);
    const [definitionIndex, setDefinitionIndex] =
        React.useState<AttributeDefinitionIndex>({});
    const initialIndex = React.useMemo<BatchAttributeIndex<T>>(() => {
        const index: BatchAttributeIndex<T> = {};
        const definitionIndex: AttributeDefinitionIndex = {};

        attributeDefinitions.forEach(def => {
            index[def.id] ??= {};
            definitionIndex[def.id] = def;
        });

        assets.forEach(a => {
            a.attributes.forEach(attribute => {
                const definitionId = attribute.definition.id;

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
        });

        setDefinitionIndex(definitionIndex);

        return index;
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

    const [index, setIndex] =
        React.useState<BatchAttributeIndex<T>>(initialIndex);

    const initialDefinitionValues = React.useMemo<
        DefinitionValuesIndex<T>
    >(() => {
        return computeAllDefinitionsValues(
            attributeDefinitions,
            subSelection,
            toKey,
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
                toKey
            );
        }
    }, [definition, index, subSelection]);

    const reset = React.useCallback(() => {
        setIndex(initialIndex);
    }, [initialIndex]);

    React.useEffect(() => {
        reset();
    }, [reset]);

    const setValue = React.useCallback(
        (
            locale: string,
            value: T | undefined,
            {add, remove, updateInput}: SetAttributeValueOptions = {}
        ) => {
            const defId = definition!.id;
            const type = attributeDefinitions.find(
                ad => ad.id === defId
            )!.fieldType;
            const key = value ? toKey(type, value) : '';

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
                                !(c[locale] as T[]).some(
                                    i => key === toKey(type, i)
                                )
                            ) {
                                (c[locale] as T[]).push(value);
                            }
                        }
                    } else if (remove) {
                        (c[locale] as T[]) = [...((c[locale] ?? []) as T[])];
                        (c[locale] as T[]) = (c[locale] as T[]).filter(
                            i => key !== toKey(type, i)
                        );
                    } else {
                        c[locale] = value;
                    }

                    na[a.id] = c;
                });

                np[defId] = na;

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
                    toKey
                );
                setDefinitionValues(
                    computeDefinitionValuesHandler<T>(definition!, values)
                );

                return np;
            });

            if (updateInput) {
                setInc(p => p + 1);
            }
        },
        [definition, subSelection]
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
                    attributeDefinitions,
                    subSelection,
                    toKey,
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
                    toKey
                );
                setDefinitionValues(
                    computeDefinitionValuesHandler<T>(definition!, values)
                );
            }
        },
        [definition, subSelection, attributeDefinitions]
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
            toKey
        );

        openModal(SavePreviewDialog, {
            actions,
            definitionIndex,
            workspaceId: assets[0].workspace.id,
            onSaved,
        });
    }, [index, initialIndex, definitionIndex, onSaved]);

    useDirtyFormPromptOutsideRouter(history.current > 0);

    return {
        inputValueInc: inc,
        values,
        setValue,
        reset,
        index,
        definitionValues,
        history,
        undo: history.current > 0 ? undo : undefined,
        redo: history.current < history.history.length - 1 ? redo : undefined,
        onSave,
    };
}
