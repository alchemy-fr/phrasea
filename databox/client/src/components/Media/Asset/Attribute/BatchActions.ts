import {
    AttributeBatchAction,
    AttributeBatchActionEnum,
} from '../../../../api/asset';
import {isSame} from '../../../../utils/comparison';
import {
    AttributeIndex,
    AttrValue,
    DefinitionIndex,
    NO_LOCALE,
} from './AttributesEditor';
import {getAttributeType} from './types';

export function getBatchActions(
    attributes: AttributeIndex<string | number>,
    definitions: DefinitionIndex,
    remoteAttrs?: AttributeIndex | undefined
): AttributeBatchAction[] {
    const actions: AttributeBatchAction[] = [];

    Object.keys(attributes).forEach((defId): void => {
        if (!definitions[defId].canEdit) {
            return;
        }

        const lv = attributes[defId];
        Object.keys(lv).forEach((locale): void => {
            const currValue = lv[locale];
            if (remoteAttrs && isSame(remoteAttrs[defId][locale], currValue)) {
                return;
            }

            if (currValue) {
                const remoteC = remoteAttrs
                    ? (remoteAttrs[defId] as {[locale: string]: AttrValue[]})
                    : undefined;
                const remoteV = remoteC
                    ? (remoteC[locale] as AttrValue[])
                    : undefined;
                if (currValue instanceof Array) {
                    if (!remoteC) {
                        actions.push({
                            action: AttributeBatchActionEnum.Set,
                            definitionId: defId,
                            value: currValue.map(_v => _v.value),
                            locale: locale !== NO_LOCALE ? locale : undefined,
                        });
                    } else {
                        currValue.forEach((v: AttrValue<string | number>) => {
                            if (!isUndefined(v.value)) {
                                const found = remoteV?.find(
                                    _v => _v.id === v.id
                                );
                                if (!found) {
                                    actions.push({
                                        action: AttributeBatchActionEnum.Add,
                                        definitionId: defId,
                                        value: v.value,
                                        locale:
                                            locale !== NO_LOCALE
                                                ? locale
                                                : undefined,
                                    });
                                } else {
                                    if (!isSame(found.value, v.value)) {
                                        actions.push({
                                            action: AttributeBatchActionEnum.Set,
                                            id: found.id,
                                            definitionId: defId,
                                            value: v.value,
                                            locale:
                                                locale !== NO_LOCALE
                                                    ? locale
                                                    : undefined,
                                        });
                                    }
                                }
                            } else if (typeof v.id === 'string') {
                                actions.push({
                                    action: AttributeBatchActionEnum.Delete,
                                    definitionId: defId,
                                    id: v.id,
                                });
                            }
                        });
                    }
                } else {
                    actions.push({
                        action: AttributeBatchActionEnum.Set,
                        definitionId: defId,
                        value: currValue.value,
                        locale: locale !== NO_LOCALE ? locale : undefined,
                    });
                }
            }
        });
    });

    if (remoteAttrs) {
        Object.keys(remoteAttrs).forEach((defId): void => {
            Object.keys(remoteAttrs[defId]).forEach((locale): void => {
                const remoteV = remoteAttrs[defId][locale];

                if (remoteV) {
                    if (remoteV instanceof Array) {
                        const attrV = attributes[defId][locale] as AttrValue<
                            string | number
                        >[];

                        remoteV.forEach(v => {
                            const found = attrV.find(_v => _v.id === v.id);
                            if (!found) {
                                actions.push({
                                    action: AttributeBatchActionEnum.Delete,
                                    definitionId: defId,
                                    id: v.id,
                                });
                            }
                        });
                    } else {
                        if (
                            !attributes[defId] ||
                            !attributes[defId][locale] ||
                            isUndefined((attributes[defId][locale] as AttrValue).value)) {
                            actions.push({
                                action: AttributeBatchActionEnum.Delete,
                                definitionId: defId,
                                id: remoteV.id,
                            });
                        }
                    }
                }
            });
        });
    }

    return actions.map(a => {
        const widget = getAttributeType(definitions[a.definitionId!].fieldType);

        return {
            ...a,
            value: widget.normalize(a.value),
        };
    });
}


function isUndefined(value: any): boolean {
    return undefined === value || '' === value;
}
