import {Asset, AttributeDefinition} from "../../types.ts";
import React from "react";
import {AttributesIndex, AttributeValues} from "./types.ts";


export function useAttributeValues(
    attributeDefinitions: AttributeDefinition[],
    assets: Asset[],
    subSelection: Asset[],
) {
    const initialIndex = React.useMemo<AttributesIndex>(() => {
        const index: AttributesIndex = {};

        attributeDefinitions.forEach((def) => {
            index[def.id] ??= {};
        });

        assets.forEach((a) => {
            a.attributes.forEach((attr) => {
                index[attr.definition.id][a.id] = attr.value;
            });
        });

        return index;
    }, [attributeDefinitions, assets]);

    const [index, setIndex] = React.useState<AttributesIndex>(initialIndex);

    const values = React.useMemo(() => {
        const values: AttributeValues = {};

        attributeDefinitions.forEach((def) => {
            values[def.id] ??= {
                values: [],
            };
        });

        subSelection.forEach((a) => {
            Object.keys(index).forEach((defId) => {
                const v = index[defId][a.id];

                const g = values[defId];
                if (g.values.length === 0) {
                    g.indeterminate = false;
                } else {
                    if (g.values.some(sv => sv !== v)) {
                        g.indeterminate = true;
                    }
                }

                g.values.push(v);
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

    const setValue = React.useCallback((defId: string, value: any) => {
        setIndex(p => {
            const np = {...p};
            const na = {...p[defId]};

            subSelection.forEach(a => {
                na[a.id] = value;
            });

            np[defId] = na;

            return np;
        });
    }, [subSelection]);

    return {
        values,
        setValue,
        reset,
        index,
    };
}
