import {Asset, AttributeDefinition} from '../../../types.ts';
import {
    BatchAttributeIndex,
    LocalizedAttributeIndex,
    ToKeyFunc, ToKeyFuncTypeScoped,
    Values,
} from '../types.ts';
import {listsAreSame} from './helper.ts';

export function computeValues<T>(
    definition: AttributeDefinition,
    subSelection: Asset[],
    index: BatchAttributeIndex<T>,
    initialIndex: BatchAttributeIndex<T>,
    toKey: ToKeyFunc<T>
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

    const toKeyForType: ToKeyFuncTypeScoped<T> = (v: T) => toKey(values.definition, v);

    subSelection.forEach(a => {
        function valueIsSame(
            a: T | T[] | undefined,
            b: T | T[] | undefined
        ): boolean {
            if (values.definition.multiple) {
                return listsAreSame(
                    (a ?? []) as T[],
                    (b ?? []) as T[],
                    (v: T) => toKeyForType(v)
                );
            }

            return (a || undefined) === (b || undefined);
        }

        const translations = index[defId][a.id];

        if (translations) {
            Object.keys(translations).forEach(l => {
                allLocales[l] = true;
                values.indeterminate[l] ??= false;

                if (
                    values.values.some(
                        (t: LocalizedAttributeIndex<T>) =>
                            !valueIsSame(t[l], translations[l])
                    )
                ) {
                    values.indeterminate[l] = true;
                    values.indeterminate.g = true;
                }
            });

            values.values.push(normalizeLocaleValues(values.definition.multiple, translations, toKeyForType));
        } else {
            values.values.push({});
            Object.keys(allLocales).forEach(l => {
                values.indeterminate[l] ??= false;

                if (
                    values.values.some(
                        (t: LocalizedAttributeIndex<T>) =>
                            !valueIsSame(t[l], undefined)
                    )
                ) {
                    values.indeterminate[l] = true;
                    values.indeterminate.g = true;
                }
            });
        }

        if (initialIndex[defId][a.id]) {
            values.originalValues.push(normalizeLocaleValues(values.definition.multiple, initialIndex[defId][a.id], toKeyForType));
        } else {
            values.originalValues.push({});
        }
    });

    return values;
}

function normalizeLocaleValues<T>(multiple: boolean, index: LocalizedAttributeIndex<T>, toKey: ToKeyFuncTypeScoped<T>): LocalizedAttributeIndex<T> {
    if (!multiple) {
        return index;
    }

    Object.keys(index).map((locale) => {
        if (index[locale]) {
            (index[locale] as T[]) = (index[locale] as T[])
                .filter(
                    (value, index, array) => {
                        const key = toKey(value);

                        return array.findIndex((v: T) => toKey(v) === key) === index;
                    }
                )
        }
    });

    return index;
}
