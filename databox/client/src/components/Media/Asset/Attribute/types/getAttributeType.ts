import {AttributeType} from '../../../../../api/types.ts';
import {AttributeTypeInstance} from './types';
import {types} from './index.ts';

export function getAttributeType(
    type: AttributeType
): AttributeTypeInstance<any> {
    const t = types[type] ?? types[AttributeType.Text]!;

    return new t();
}
