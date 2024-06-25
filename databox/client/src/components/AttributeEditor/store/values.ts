import {Asset, AttributeDefinition, StateSetter} from "../../../types.ts";
import {AttributeIndex, DefinitionValuesIndex, LocalizedAttributeIndex, ToKeyFunc, Values} from "../types.ts";
import {computeDefinitionValues} from "./definitionValues.ts";
import {listsAreSame} from "./helper.ts";

export function computeValues<T>(
    definition: AttributeDefinition,
    subSelection: Asset[],
    index: AttributeIndex<T>,
    initialIndex: AttributeIndex<T>,
    toKey: ToKeyFunc<T>,
    setDefinitionValues?: StateSetter<DefinitionValuesIndex<T>>,
): Values<T> {
    const values: Values<T> = {
        definition,
        values: [],
        originalValues: [],
        indeterminate: {
            g: false,
        },
    };

    const defId = definition.id;
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

        if (initialIndex[defId][a.id]) {
            values.originalValues.push(initialIndex[defId][a.id]);
        } else {
            values.originalValues.push({});
        }
    });

    if (setDefinitionValues) {
        computeDefinitionValues<T>(setDefinitionValues, definition, values);
    }

    return values;
}
