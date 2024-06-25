import {Asset, AttributeDefinition} from "../../types.ts";
import React from "react";
import {
    AttributeIndex,
    AttributesHistory,
    DefinitionValuesIndex,
    SetAttributeValueOptions,
    ToKeyFunc,
    Values
} from "./types";
import {NO_LOCALE} from "../Media/Asset/Attribute/AttributesEditor";
import {computeValues} from "./store/values.ts";
import {computeAllDefinitionsValues} from "./store/definitionValues.ts";

export function useAttributeValues<T>(
    attributeDefinitions: AttributeDefinition[],
    assets: Asset[],
    subSelection: Asset[],
    toKey: ToKeyFunc<T>,
    definition: AttributeDefinition | undefined,
) {
    const [inc, setInc] = React.useState(0);
    const initialIndex = React.useMemo<AttributeIndex<T>>(() => {
        const index: AttributeIndex<T> = {};

        attributeDefinitions.forEach((def) => {
            index[def.id] ??= {};
        });

        assets.forEach((a) => {
            a.attributes.forEach((attr) => {
                index[attr.definition.id][a.id] ??= {};
                index[attr.definition.id][a.id][attr.locale ?? NO_LOCALE] = attr.value;
            });
        });

        return index;
    }, [attributeDefinitions, assets]);

    const [history, setHistory] = React.useState<AttributesHistory<T>>({
        history: [initialIndex],
        current: 0,
    });


    const [index, setIndex] = React.useState<AttributeIndex<T>>(initialIndex);

    const initialDefinitionValues = React.useMemo<DefinitionValuesIndex<T>>(() => {
        return computeAllDefinitionsValues(attributeDefinitions, subSelection, toKey, index);
    }, [initialIndex, subSelection]);

    React.useEffect(() => {
        setDefinitionValues(initialDefinitionValues);
    }, [subSelection]);

    const [definitionValues, setDefinitionValues] = React.useState<DefinitionValuesIndex<T>>(initialDefinitionValues);

    const [values, setValues] = React.useState<(Values<T>) | undefined>();

    React.useEffect(() => {
        if (definition) {
            setValues(computeValues<T>(definition, subSelection, index, initialIndex, toKey, setDefinitionValues));
        } else {
            setValues(undefined);
        }
    }, [subSelection, definition]);

    const reset = React.useCallback(() => {
        setIndex(initialIndex);
    }, [initialIndex]);

    React.useEffect(() => {
        reset();
    }, [reset]);

    const setValue = React.useCallback((locale: string, value: T | undefined, {
        add,
        remove,
        updateInput,
    }: SetAttributeValueOptions = {}) => {
        const defId = definition!.id;
        const type = attributeDefinitions.find(ad => ad.id === defId)!.fieldType;
        const key = value ? toKey(type, value) : '';

        setIndex(p => {
            const np = {...p};
            const na = {...p[defId]};

            subSelection.forEach(a => {
                const c = {...(na[a.id] ?? {})};

                if (add) {
                    if (value) {
                        (c[locale] as T[]) = [...((c[locale] ?? []) as T[])];
                        if (!(c[locale] as T[]).some(i => key === toKey(type, i))) {
                            (c[locale] as T[]).push(value);
                        }
                    }
                } else if (remove) {
                    (c[locale] as T[]) = [...((c[locale] ?? []) as T[])];
                    (c[locale] as T[]) = (c[locale] as T[]).filter(i => key !== toKey(type, i));
                } else {
                    c[locale] = value;
                }

                na[a.id] = c;
            });

            np[defId] = na;

            setHistory(ph => ({
                history: ph.history.slice(0, ph.current + 1).concat([np]),
                current: ph.current + 1,
            }));
            setValues(computeValues<T>(definition!, subSelection, np, initialIndex, toKey, setDefinitionValues));

            return np;
        });

        if (updateInput) {
            setInc(p => p + 1);
        }
    }, [definition, subSelection]);

    const applyHistory = React.useCallback((newIndex: AttributeIndex<T>) => {
        setIndex(newIndex);
        setDefinitionValues(computeAllDefinitionsValues<T>(attributeDefinitions, subSelection, toKey, newIndex));
        setInc(p => p + 1);
        if (definition) {
            setValues(computeValues<T>(definition, subSelection, newIndex, initialIndex, toKey));
        }
    }, [definition, subSelection, attributeDefinitions]);

    const undo = React.useCallback(() => {
        setHistory(ph => {
            const i = ph.current - 1;
            applyHistory(ph.history[i]);

            return {
                ...ph,
                current: i,
            }
        })
    }, [applyHistory]);

    const redo = React.useCallback(() => {
        setHistory(ph => {
            const i = ph.current + 1;
            applyHistory(ph.history[i]);

            return {
                ...ph,
                current: i,
            }
        })
    }, [applyHistory]);

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
    };
}
