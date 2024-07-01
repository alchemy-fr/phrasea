import {Asset, AttributeDefinition} from "../../../types";
import {
    AttributeIndex,
    DefinitionValuesIndex,
    LocalizedAttributeIndex,
    ToKeyFunc,
    Values
} from "../types";
import {listsAreSame} from "./helper";

export function computeAllDefinitionsValues<T>(
    attributeDefinitions: AttributeDefinition[],
    subSelection: Asset[],
    toKey: ToKeyFunc<T>,
    index: AttributeIndex<T>
) {
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
}

export function computeDefinitionValuesHandler<T>(
    definition: AttributeDefinition,
    values: Values<T>
) {
    return (p: DefinitionValuesIndex<T>): DefinitionValuesIndex<T> => {
        const n = {...p};
        const indeterminate = values.indeterminate;

        n[definition.id] = {
            indeterminate,
            value: values.values[0],
        }

        return n;
    }
}
