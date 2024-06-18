import {Asset, AttributeDefinition} from "../../types.ts";
import React from "react";
import {AttributeIndex, AttributeValues, LocalizedAttributeIndex} from "./types.ts";
import {NO_LOCALE} from "../Media/Asset/Attribute/AttributesEditor.tsx";


export function useAttributeValues<T>(
    attributeDefinitions: AttributeDefinition[],
    assets: Asset[],
    subSelection: Asset[],
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

    const [index, setIndex] = React.useState<AttributeIndex<T>>(initialIndex);

    const values = React.useMemo(() => {
        const values: AttributeValues = {};

        attributeDefinitions.forEach((def) => {
            values[def.id] ??= {
                definition: def,
                values: [],
                originalValues: [],
                indeterminate: {
                    g: false,
                },
            };
        });

        subSelection.forEach((a) => {
            Object.keys(index).forEach((defId) => {
                const g = values[defId];

                const translations = index[defId][a.id];

                if (translations) {
                    Object.keys(translations).forEach((l) => {
                       g.indeterminate[l] ??= false;

                        if (g.values.some((t: LocalizedAttributeIndex<T>) => t[l] !== translations[l])) {
                            g.indeterminate[l] = true;
                            g.indeterminate.g = true;
                        }
                    });

                    g.values.push(translations);

                    if (initialIndex[defId][a.id]) {
                        g.originalValues.push(initialIndex[defId][a.id]);
                    }
                }
            });
        });

        return values;
    }, [subSelection, index]);

    const reset = React.useCallback(() => {
        setIndex(initialIndex);
    }, [initialIndex]);

    React.useEffect(() => {
        reset();
    }, [reset]);

    const setValue = React.useCallback((defId: string, locale: string, value: any, updateInput?: boolean) => {
        setIndex(p => {
            const np = {...p};
            const na = {...p[defId]};

            subSelection.forEach(a => {
                if (na[a.id]) {
                    na[a.id] = {...na[a.id]};
                } else {
                    na[a.id] = {};
                }
                na[a.id][locale] = value;
            });

            np[defId] = na;

            return np;
        });

        if (updateInput) {
            setInc(p => p + 1);
        }
    }, [subSelection]);

    return {
        inputValueInc: inc,
        values,
        setValue,
        reset,
        index,
    };
}
