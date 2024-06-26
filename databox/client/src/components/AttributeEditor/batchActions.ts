import {isSame} from '../../utils/comparison';
import {
    AttributeDefinitionIndex,
    AttributeIndex,
    DiffGroupIndex,
    ToKeyFunc,
    ToKeyFuncTypeScoped
} from "./types.ts";
import {NO_LOCALE} from "../Media/Asset/Attribute/AttributesEditor.tsx";
import {AttributeBatchAction, AttributeBatchActionEnum,} from '../../api/asset';
import {normalizeList} from "./store/normalize.ts";

export function getBatchActions<T>(
    remoteAttrs: AttributeIndex<T>,
    attributes: AttributeIndex<T>,
    definitions: AttributeDefinitionIndex,
    toKey: ToKeyFunc<T>,
): AttributeBatchAction[] {
    const actions: AttributeBatchAction[] = [];

    Object.keys(attributes).forEach((defId): void => {
        const definition = definitions[defId];
        if (!definition.canEdit) {
            return;
        }

        const groups: DiffGroupIndex<T> = {};

        const av = attributes[defId];
        Object.keys(av).forEach((assetId): void => {
            const lv = av[assetId];
            Object.keys(lv).forEach((locale): void => {
                const currValue = lv[locale];
                if (isSame(remoteAttrs[defId]?.[assetId]?.[locale], currValue)) {
                    return;
                }

                if (currValue && (!definition.multiple || currValue.length > 0)) {
                    const toKeyForType: ToKeyFuncTypeScoped<T> = (v) => toKey(definition.fieldType, v);
                    const key = definition.multiple ? normalizeList<T>(currValue as T[], toKeyForType).toString() : toKeyForType(currValue);
                    groups[locale] ??= {};
                    groups[locale][key] ??= {
                        ids: [],
                        value: currValue,
                    };
                    groups[locale][key].ids.push(assetId);
                }
            });
        });

        Object.keys(groups).forEach(locale => {
            const g = groups[locale];
            Object.keys(g).forEach(key => {
                const v = g[key];

                actions.push({
                    action: AttributeBatchActionEnum.Set,
                    assets: v.ids,
                    definitionId: defId,
                    value: v.value,
                    locale: locale !== NO_LOCALE ? locale : undefined,
                });
            });
        });
    });

    Object.keys(remoteAttrs).forEach((defId): void => {
        const definition = definitions[defId];
        if (!definition.canEdit) {
            return;
        }

        const groups: DiffGroupIndex<T> = {};

        const av = remoteAttrs[defId];
        Object.keys(av).forEach((assetId): void => {
            const lv = av[assetId];
            Object.keys(lv).forEach((locale): void => {
                const initialValue = lv[locale];
                if (!initialValue) {
                    return;
                }

                const currValue = attributes?.[defId]?.[assetId]?.[locale];

                if (definition.multiple) {
                    if ((initialValue as T[]).length === 0) {
                        return;
                    }

                    if (currValue && (currValue as T[]).length > 0) {
                        return;
                    }
                } else if (currValue !== undefined) {
                    return;
                }

                const toKeyForType: ToKeyFuncTypeScoped<T> = (v) => toKey(definition.fieldType, v);
                const key = definition.multiple ? normalizeList<T>(initialValue as T[], toKeyForType).toString() : toKeyForType(initialValue);
                groups[locale] ??= {};
                groups[locale][key] ??= {
                    ids: [],
                    value: initialValue,
                };
                groups[locale][key].ids.push(assetId);
            });
        });

        Object.keys(groups).forEach(locale => {
            const g = groups[locale];
            Object.keys(g).forEach(key => {
                const v = g[key];

                actions.push({
                    action: AttributeBatchActionEnum.Delete,
                    assets: v.ids,
                    previous: v.value,
                    definitionId: defId,
                    locale: locale !== NO_LOCALE ? locale : undefined,
                });
            });
        });
    });

    return actions;
}
