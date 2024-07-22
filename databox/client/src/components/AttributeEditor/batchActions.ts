import {isSame} from '../../utils/comparison';
import {
    AttributeDefinitionIndex,
    BatchAttributeIndex,
    DiffGroupIndex,
    ExtraAttributeDefinition,
    CreateToKeyFunc,
    ToKeyFuncTypeScoped,
} from './types.ts';
import {NO_LOCALE} from '../Media/Asset/Attribute/AttributesEditor.tsx';
import {AttributeBatchAction, AttributeBatchActionEnum} from '../../api/asset';
import {pushUnique} from '../../utils/array.ts';
import {Asset, Attribute} from '../../types.ts';

export function getBatchActions<T>(
    assets: Asset[],
    initialAttributes: BatchAttributeIndex<T>,
    attributes: BatchAttributeIndex<T>,
    definitions: AttributeDefinitionIndex,
    createToKey: CreateToKeyFunc<T>
): AttributeBatchAction[] {
    const setGroups: DiffGroupIndex<T> = {};
    const addGroups: DiffGroupIndex<T> = {};
    const deleteGroups: DiffGroupIndex<T> = {};

    Object.keys(attributes).forEach((defId): void => {
        const definition = definitions[defId];
        if (!definition.canEdit) {
            return;
        }

        const toKey: ToKeyFuncTypeScoped<T> = createToKey(definition.fieldType);
        const av = attributes[defId];
        Object.keys(av).forEach((assetId): void => {
            const asset = assets.find(a => a.id === assetId)!;
            const lv = av[assetId];
            Object.keys(lv).forEach((locale): void => {
                const currValue = lv[locale];
                const initialValue =
                    initialAttributes[defId]?.[assetId]?.[locale];

                if (isSame(initialValue, currValue)) {
                    return;
                }

                if (currValue) {
                    if (definition.multiple) {
                        if ((currValue as T[]).length > 0) {
                            const normalizedInitialValues: T[] =
                                (initialValue as T[]) ?? [];

                            (currValue as T[]).forEach(v => {
                                const key = toKey(v);

                                if (
                                    !normalizedInitialValues.some(
                                        (i: T) => toKey(i) === key
                                    )
                                ) {
                                    addGroups[defId] ??= {};
                                    addGroups[defId][locale] ??= {};
                                    addGroups[defId][locale][key] ??= {
                                        assetIds: [],
                                        value: v,
                                    };
                                    pushUnique(
                                        addGroups[defId][locale][key].assetIds,
                                        assetId
                                    );
                                }
                            });

                            deleteNonPresent(
                                currValue as T[],
                                normalizedInitialValues,
                                defId,
                                asset,
                                locale,
                                toKey,
                                deleteGroups
                            );
                        }
                    } else {
                        const key = toKey(currValue);
                        setGroups[defId] ??= {};
                        setGroups[defId][locale] ??= {};
                        setGroups[defId][locale][key] ??= {
                            assetIds: [],
                            value: currValue,
                        };
                        pushUnique(
                            setGroups[defId][locale][key].assetIds,
                            assetId
                        );
                    }
                }
            });
        });
    });

    Object.keys(initialAttributes).forEach((defId): void => {
        const definition = definitions[defId];
        if (!definition.canEdit) {
            return;
        }

        const toKeyForType = createToKey(definition.fieldType);
        const av = initialAttributes[defId];
        Object.keys(av).forEach((assetId): void => {
            const asset = assets.find(a => a.id === assetId)!;

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

                    if (
                        currValue !== undefined &&
                        (currValue as T[]).length > 0
                    ) {
                        return;
                    }

                    deleteNonPresent(
                        (currValue as T[]) ?? [],
                        initialValue as T[],
                        defId,
                        asset,
                        locale,
                        toKeyForType,
                        deleteGroups
                    );
                } else {
                    if (currValue !== undefined) {
                        return;
                    }

                    const key = toKeyForType(initialValue);
                    deleteGroups[defId] ??= {};
                    deleteGroups[defId][locale] ??= {};
                    deleteGroups[defId][locale][key] ??= {
                        assetIds: [],
                        attributeIds: [],
                        value: initialValue,
                    };
                    pushUnique(
                        deleteGroups[defId][locale][key].assetIds,
                        assetId
                    );

                    addAttributeIdsToDeleteGroup(
                        asset,
                        defId,
                        locale,
                        key,
                        toKeyForType,
                        deleteGroups
                    );
                }
            });
        });
    });

    return computeActionsFromGroups<T>(setGroups, addGroups, deleteGroups);
}

function computeActionsFromGroups<T>(
    setGroups: DiffGroupIndex<T>,
    addGroups: DiffGroupIndex<T>,
    deleteGroups: DiffGroupIndex<T>
): AttributeBatchAction[] {
    const actions: AttributeBatchAction[] = [];

    Object.keys(setGroups).forEach(defId => {
        Object.keys(setGroups[defId]).forEach(locale => {
            const g = setGroups[defId][locale];
            Object.keys(g).forEach(key => {
                const v = g[key];

                actions.push({
                    action: AttributeBatchActionEnum.Set,
                    assets: v.assetIds,
                    definitionId: defId,
                    value: v.value,
                    locale: locale !== NO_LOCALE ? locale : undefined,
                });
            });
        });
    });

    Object.keys(addGroups).forEach(defId => {
        Object.keys(addGroups[defId]).forEach(locale => {
            const g = addGroups[defId][locale];
            Object.keys(g).forEach(key => {
                const v = g[key];

                actions.push({
                    action: AttributeBatchActionEnum.Add,
                    assets: v.assetIds,
                    definitionId: defId,
                    value: v.value,
                    locale: locale !== NO_LOCALE ? locale : undefined,
                });
            });
        });
    });

    Object.keys(deleteGroups).forEach(defId => {
        Object.keys(deleteGroups[defId]).forEach(locale => {
            const g = deleteGroups[defId][locale];
            Object.keys(g).forEach(key => {
                const v = g[key];

                actions.push({
                    action: AttributeBatchActionEnum.Delete,
                    assets: v.assetIds,
                    ids: v.attributeIds,
                    value: v.value,
                    definitionId: defId,
                    locale: locale !== NO_LOCALE ? locale : undefined,
                });
            });
        });
    });

    return actions;
}

function deleteNonPresent<T>(
    list: T[],
    referenceList: T[],
    defId: string,
    asset: Asset,
    locale: string,
    toKeyForType: ToKeyFuncTypeScoped<T>,
    deleteGroups: DiffGroupIndex<T>
) {
    referenceList.forEach(v => {
        const key = toKeyForType(v);

        if (!list.some((i: T) => toKeyForType(i) === key)) {
            deleteGroups[defId] ??= {};
            deleteGroups[defId][locale] ??= {};
            deleteGroups[defId][locale][key] ??= {
                assetIds: [],
                attributeIds: [],
                value: v,
            };
            pushUnique(deleteGroups[defId][locale][key].assetIds, asset.id);

            addAttributeIdsToDeleteGroup(
                asset,
                defId,
                locale,
                key,
                toKeyForType,
                deleteGroups
            );
        }
    });
}

function addAttributeIdsToDeleteGroup<T>(
    asset: Asset,
    defId: string,
    locale: string,
    key: string,
    toKeyForType: ToKeyFuncTypeScoped<T>,
    deleteGroups: DiffGroupIndex<T>
) {
    if (defId === ExtraAttributeDefinition.Tags) {
        const attributeIds = deleteGroups[defId][locale][key].attributeIds!;
        if (!attributeIds.includes(key)) {
            attributeIds.push(key);
        }

        return;
    }

    const attributeIds = asset.attributes
        .filter((a: Attribute): boolean => {
            return (
                a.definition.id === defId &&
                (a.locale ?? NO_LOCALE) === locale &&
                toKeyForType(a.value) === key
            );
        })
        .map(a => a.id);

    if (attributeIds.length === 0) {
        throw new Error('No attribute found for action');
    }

    attributeIds.forEach((attributeId: string) => {
        deleteGroups[defId][locale][key].attributeIds!.push(attributeId);
    });
}
