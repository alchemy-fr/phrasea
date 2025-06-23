import {AttributeIndex, DefinitionIndex} from './AttributesEditor';
import {getAttributeType} from './types';
import {NO_LOCALE} from './constants.ts';

type AttributeInput = {
    definitionId: string;
    locale?: string | undefined;
    origin?: 'human' | 'machine';
    originVendor?: string;
    originUserId?: string;
    originVendorContext?: string;
    confidence?: number;
    value: any;
};

export function getAttributeList(
    attributes: AttributeIndex<string | number>,
    definitions: DefinitionIndex
): AttributeInput[] {
    const list: AttributeInput[] = [];

    Object.keys(attributes).forEach((defId): void => {
        const definition = definitions[defId];
        if (!definition.canEdit) {
            return;
        }

        const widget = getAttributeType(definition.fieldType);

        const lv = attributes[defId];
        Object.keys(lv).forEach((locale): void => {
            const currValue = lv[locale];
            const inputLocal = locale !== NO_LOCALE ? locale : undefined;

            if (currValue) {
                if (currValue instanceof Array) {
                    currValue.forEach(_v => {
                        list.push({
                            definitionId: defId,
                            locale: inputLocal,
                            value: widget.normalize(_v.value),
                        });
                    });
                } else {
                    list.push({
                        definitionId: defId,
                        locale: inputLocal,
                        value: widget.normalize(currValue.value),
                    });
                }
            }
        });
    });

    return list;
}
