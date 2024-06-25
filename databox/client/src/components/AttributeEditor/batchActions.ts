import {isSame} from '../../utils/comparison';
import {AttributeDefinitionIndex, AttributeIndex, DiffGroupIndex, ToKeyFunc, ToKeyFuncTypeScoped} from "./types.ts";
import {NO_LOCALE} from "../Media/Asset/Attribute/AttributesEditor.tsx";
import {
    AttributeBatchAction,
    AttributeBatchActionEnum,
} from '../../api/asset';
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

                if (currValue) {
                    const toKeyForType: ToKeyFuncTypeScoped<T> = (v) => toKey(definition.fieldType, v);

                    const key = definition.multiple ? normalizeList<T>(currValue as T[], toKeyForType).toString() : toKeyForType(currValue);
                    console.log('key', key);
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

    // TODO
    // if (remoteAttrs) {
    //     Object.keys(remoteAttrs).forEach((defId): void => {
    //         Object.keys(remoteAttrs[defId]).forEach((locale): void => {
    //             const remoteV = remoteAttrs[defId][locale];
    //
    //             if (remoteV) {
    //                 if (remoteV instanceof Array) {
    //                     const attrV = attributes[defId][locale] as T[];
    //
    //                     remoteV.forEach(v => {
    //                         const found = attrV.find(_v => _v.id === v.id);
    //                         if (!found) {
    //                             actions.push({
    //                                 action: AttributeBatchActionEnum.Delete,
    //                                 definitionId: defId,
    //                                 id: v.id,
    //                             });
    //                         }
    //                     });
    //                 } else {
    //                     if (
    //                         !attributes[defId] ||
    //                         !attributes[defId][locale] ||
    //                         (attributes[defId][locale] as T).value ===
    //                         undefined
    //                     ) {
    //                         actions.push({
    //                             action: AttributeBatchActionEnum.Delete,
    //                             definitionId: defId,
    //                             id: remoteV.id,
    //                         });
    //                     }
    //                 }
    //             }
    //         });
    //     });

    return actions;
}
