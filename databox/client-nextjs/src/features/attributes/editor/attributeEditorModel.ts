import type {
    Attribute,
    AttributeBatchAction,
    AttributeDefinition,
} from '@/types/api';
import {AttributeBatchActionEnum} from '@/types/api';
import {NO_LOCALE} from '@/lib/utils/locale';
import {deepEquals} from '@/lib/utils/misc';
import {getAttributeType} from '@/features/attributes/types/registry';

export type AttrValue = {
    /** attribute id (existing) or a temporary local id */
    id: string;
    value: unknown;
    invalid?: boolean;
};

/** definitionId -> locale -> value(s) */
export type AttributeIndex = Record<
    string,
    Record<string, AttrValue | AttrValue[] | undefined>
>;
export type DefinitionIndex = Record<string, AttributeDefinition>;

export function buildAttributeIndex(
    definitions: DefinitionIndex,
    attributes: Attribute[]
): AttributeIndex {
    const index: AttributeIndex = {};
    Object.keys(definitions).forEach(id => {
        index[id] = {};
    });
    for (const a of attributes) {
        const def = definitions[a.definition.id];
        if (!def) {
            continue;
        }
        const locale = a.locale || NO_LOCALE;
        const typeDef = getAttributeType(def.type);
        const v: AttrValue = {
            id: a.id,
            value: typeDef.denormalize ? typeDef.denormalize(a.value) : a.value,
            invalid: a.invalid ?? false,
        };
        index[def.id] ??= {};
        if (def.multiple) {
            const list =
                (index[def.id][locale] as AttrValue[] | undefined) ?? [];
            list.push(v);
            index[def.id][locale] = list;
        } else {
            index[def.id][locale] = v;
        }
    }

    return index;
}

function normalizeValue(def: AttributeDefinition, value: unknown): unknown {
    const typeDef = getAttributeType(def.type);

    return typeDef.normalize ? typeDef.normalize(value) : value;
}

function isEmpty(v: unknown): boolean {
    return v === undefined || v === null || v === '';
}

/**
 * Computes the batch actions (set / add / delete) turning `remote` into
 * `current` for the editable definitions.
 */
export function computeBatchActions(
    current: AttributeIndex,
    definitions: DefinitionIndex,
    remote: AttributeIndex
): AttributeBatchAction[] {
    const actions: AttributeBatchAction[] = [];

    for (const defId of Object.keys(current)) {
        const def = definitions[defId];
        if (!def?.canEdit) {
            continue;
        }
        const locales = new Set([
            ...Object.keys(current[defId] ?? {}),
            ...Object.keys(remote[defId] ?? {}),
        ]);
        for (const locale of locales) {
            const curr = current[defId]?.[locale];
            const rem = remote[defId]?.[locale];
            const localeParam = locale !== NO_LOCALE ? locale : undefined;
            if (deepEquals(curr, rem)) {
                continue;
            }

            if (def.multiple) {
                const currList = (curr as AttrValue[] | undefined) ?? [];
                const remList = (rem as AttrValue[] | undefined) ?? [];
                if (remList.length === 0) {
                    const values = currList
                        .map(v => normalizeValue(def, v.value))
                        .filter(v => !isEmpty(v));
                    if (values.length > 0) {
                        actions.push({
                            action: AttributeBatchActionEnum.Set,
                            definitionId: defId,
                            value: values,
                            locale: localeParam,
                        });
                    }
                    continue;
                }
                for (const v of currList) {
                    const found = remList.find(r => r.id === v.id);
                    const value = normalizeValue(def, v.value);
                    if (!found) {
                        if (!isEmpty(value)) {
                            actions.push({
                                action: AttributeBatchActionEnum.Add,
                                definitionId: defId,
                                value,
                                locale: localeParam,
                            });
                        }
                    } else if (
                        !deepEquals(normalizeValue(def, found.value), value)
                    ) {
                        if (isEmpty(value)) {
                            actions.push({
                                action: AttributeBatchActionEnum.Delete,
                                definitionId: defId,
                                id: found.id,
                            });
                        } else {
                            actions.push({
                                action: AttributeBatchActionEnum.Set,
                                id: found.id,
                                definitionId: defId,
                                value,
                                locale: localeParam,
                            });
                        }
                    }
                }
                for (const r of remList) {
                    if (!currList.some(v => v.id === r.id)) {
                        actions.push({
                            action: AttributeBatchActionEnum.Delete,
                            definitionId: defId,
                            id: r.id,
                        });
                    }
                }
            } else {
                const c = curr as AttrValue | undefined;
                const r = rem as AttrValue | undefined;
                const value = c ? normalizeValue(def, c.value) : undefined;
                if (isEmpty(value)) {
                    if (r) {
                        actions.push({
                            action: AttributeBatchActionEnum.Delete,
                            definitionId: defId,
                            id: r.id,
                        });
                    }
                } else if (
                    !r ||
                    !deepEquals(normalizeValue(def, r.value), value)
                ) {
                    actions.push({
                        action: AttributeBatchActionEnum.Set,
                        definitionId: defId,
                        value,
                        locale: localeParam,
                    });
                }
            }
        }
    }

    return actions;
}

/**
 * Builds "set" actions for a fresh asset (upload form) from the index.
 */
export function indexToCreateActions(
    index: AttributeIndex,
    definitions: DefinitionIndex
): AttributeBatchAction[] {
    return computeBatchActions(index, definitions, {});
}
