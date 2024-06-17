import {Asset, AttributeDefinition} from "../../types.ts";
import React from "react";
import {AttributeIndex, AttributeValues} from "./types.ts";
import {NO_LOCALE} from "../Media/Asset/Attribute/AttributesEditor.tsx";


export function useAttributeValues(
    attributeDefinitions: AttributeDefinition[],
    assets: Asset[],
    subSelection: Asset[],
    currentLocale: string,
) {
    const [inc, setInc] = React.useState(0);
    const initialIndex = React.useMemo<AttributeIndex>(() => {
        const index: AttributeIndex = {};

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

    const [index, setIndex] = React.useState<AttributeIndex>(initialIndex);

    const values = React.useMemo(() => {
        const values: AttributeValues = {};

        attributeDefinitions.forEach((def) => {
            values[def.id] ??= {
                definition: def,
                values: [],
                originalValues: [],
            };
        });

        subSelection.forEach((a) => {
            Object.keys(index).forEach((defId) => {
                const g = values[defId];
                const l = g.definition.translatable ? (currentLocale ?? NO_LOCALE) : NO_LOCALE;

                const translations = index[defId][a.id];
                const v = translations ? translations[l] : undefined;
                if (g.values.length === 0) {
                    g.indeterminate = false;
                } else {
                    if (g.values.some(sv => sv !== v)) {
                        g.indeterminate = true;
                    }
                }

                g.values.push(v);

                if (initialIndex[defId][a.id]) {
                    g.originalValues.push(initialIndex[defId][a.id][l]);
                }
            });
        });

        return values;
    }, [subSelection, index, currentLocale]);

    const reset = React.useCallback(() => {
        setIndex(initialIndex);
    }, [initialIndex]);

    React.useEffect(() => {
        reset();
    }, [reset]);

    const setValue = React.useCallback((defId: string, value: any, updateInput?: boolean) => {
        setIndex(p => {
            const np = {...p};
            const na = {...p[defId]};

            subSelection.forEach(a => {
                na[a.id] = value;
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
