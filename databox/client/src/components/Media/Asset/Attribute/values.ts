import {AttributeType as AttributeTypeEnum} from '../../../../api/types.ts';
import {AttrValue} from './AttributesEditor.tsx';

let idInc = 1;

export function createNewValue(type: string): AttrValue<number> {
    switch (type) {
        default:
        case AttributeTypeEnum.Text:
            return {
                id: idInc++,
                value: '',
            };
    }
}
