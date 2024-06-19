import {Asset, AttributeDefinition} from "../../types.ts";
import React from "react";
import {
    AttributeIndex,
    DefinitionValuesIndex,
    LocalizedAttributeIndex,
    SetAttributeValueOptions,
    ToKeyFunc,
    ToKeyFuncTypeScoped,
    Values
} from "./types.ts";
import {NO_LOCALE} from "../Media/Asset/Attribute/AttributesEditor.tsx";


export function useAttributeValues<T>(
    attributeDefinitions: AttributeDefinition[],
    assets: Asset[],
    subSelection: Asset[],
    toKey: ToKeyFunc<T>,
    definition: AttributeDefinition | undefined,
) {
    const [inc, setInc] = React.useState(0);

    const initialIndex = React.useMemo<AttributeIndex<T| T[]>>(() => {
        const index: AttributeIndex<T | T[]> = {};

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

    const [index, setIndex] = React.useState<AttributeIndex<T | T[]>>(initialIndex);

    const initialDefinitionValues = React.useMemo<DefinitionValuesIndex<T>>(() => {
        const tree: DefinitionValuesIndex<T> = {};

        attributeDefinitions.forEach((def) => {
            const defId = def.id;
            const values: Values<T> = {
                definition: def,
                values: [],
                originalValues: [],
                indeterminate: {
                    g: false,
                },
            };

            const allLocales: Record<string, true> = {};

            subSelection.forEach((a) => {
                function valueIsSame(a: T | T[] | undefined, b: T | T[] | undefined): boolean {
                    if (def.multiple) {
                        return listsAreSame((a ?? []) as T[], (b ?? []) as T[], (v: T) => toKey(values.definition.fieldType, v));
                    }

                    return (a || undefined) === (b || undefined)
                }

                const translations = index[defId][a.id];

                if (translations) {
                    Object.keys(translations).forEach((l) => {
                        allLocales[l] = true;
                        values.indeterminate[l] ??= false;

                        if (values.values.some((t: LocalizedAttributeIndex<T>) => !valueIsSame(t[l], translations[l]))) {
                            values.indeterminate[l] = true;
                            values.indeterminate.g = true;
                        }
                    });
                    if (!values.indeterminate.g) {
                        values.values.push(translations as LocalizedAttributeIndex<T>);
                    }
                } else {
                    values.values.push({});
                    Object.keys(allLocales).forEach((l) => {
                        values.indeterminate[l] ??= false;

                        if (values.values.some((t: LocalizedAttributeIndex<T>) => !valueIsSame(t[l], undefined))) {
                            values.indeterminate[l] = true;
                            values.indeterminate.g = true;
                        }
                    });
                }
            });

            tree[defId] = {
                value: values.values[0] ?? {},
                indeterminate: values.indeterminate,
            };
        });

        return tree;
    }, [initialIndex, subSelection]);

    React.useEffect(() => {
        setDefinitionValues(initialDefinitionValues);
    }, [subSelection]);

    const [definitionValues, setDefinitionValues] = React.useState<DefinitionValuesIndex<T>>(initialDefinitionValues);

    const values = React.useMemo<Values | undefined>(() => {
        if (!definition) {
            return;
        }

        const defId = definition.id;

        const values: Values = {
            definition,
            values: [],
            originalValues: [],
            indeterminate: {
                g: false,
            },
        };

        const allLocales: Record<string, true> = {};

        subSelection.forEach((a) => {
            function valueIsSame(a: T | T[] | undefined, b: T | T[] | undefined): boolean {
                if (values.definition.multiple) {
                    return listsAreSame((a ?? []) as T[], (b ?? []) as T[], (v: T) => toKey(values.definition.fieldType, v));
                }

                return (a || undefined) === (b || undefined)
            }

            const translations = index[defId][a.id];

            if (translations) {
                Object.keys(translations).forEach((l) => {
                    allLocales[l] = true;
                    values.indeterminate[l] ??= false;

                    if (values.values.some((t: LocalizedAttributeIndex<T>) => !valueIsSame(t[l], translations[l]))) {
                        values.indeterminate[l] = true;
                        values.indeterminate.g = true;
                    }
                });

                values.values.push(translations);

                if (initialIndex[defId][a.id]) {
                    values.originalValues.push(initialIndex[defId][a.id]);
                }
            } else {
                values.values.push({});
                Object.keys(allLocales).forEach((l) => {
                    values.indeterminate[l] ??= false;

                    if (values.values.some((t: LocalizedAttributeIndex<T>) => !valueIsSame(t[l], undefined))) {
                        values.indeterminate[l] = true;
                        values.indeterminate.g = true;
                    }
                });
            }
        });

        return values;
    }, [subSelection, definition, index]);

    React.useEffect(() => {
        if (definition && values && values.definition.id === definition.id) {
            setDefinitionValues(p => {
                const n = {...p};
                const indeterminate = values.indeterminate;

                n[definition.id] = {
                    indeterminate,
                    value: values.values[0],
                }
                return n;
            });
        }
    }, [values]);

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
                const c= {...(na[a.id] ?? {})};

                if (add) {
                    if (value) {
                        (c[locale] as T[]) ??= [];
                        if (!(c[locale] as T[]).some(i => key === toKey(type, i))) {
                            (c[locale] as T[]).push(value);
                        }

                    }
                } else if (remove) {
                    (c[locale] as T[]) ??= [];
                    (c[locale] as T[]) = (c[locale] as T[]).filter(i => key !== toKey(type, i));
                } else {
                    c[locale] = value;
                }

                na[a.id] = c;
            });

            np[defId] = na;

            return np;
        });

        if (updateInput) {
            setInc(p => p + 1);
        }
    }, [definition, subSelection]);

    return {
        inputValueInc: inc,
        values,
        setValue,
        reset,
        index,
        definitionValues,
    };
}

function normalizeList<T>(a: T[], toKey: ToKeyFuncTypeScoped<T>): string[] {
    return a
        .map(toKey)
        .sort((a, b) => a.localeCompare(b));
}

function listsAreSame<T>(a: T[], b: T[], toKey: ToKeyFuncTypeScoped<T>): boolean {
    if (a.length !== b.length) {
        return false;
    }

    const an = normalizeList<T>(a, toKey);
    const bn = normalizeList<T>(b, toKey);
    for (let i = 0; i < an.length; i++) {
        if (an[i] !== bn[i]) {
            return false;
        }
    }

    return true;
}
