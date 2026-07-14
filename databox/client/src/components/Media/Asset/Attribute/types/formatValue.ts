import {AttributeType} from '../../../../../api/types.ts';
import {AttributeFormatterProps} from './types';
import {ReactNode} from 'react';
import {getAttributeType} from './getAttributeType.ts';

export function formatValue(
    type: AttributeType,
    props: AttributeFormatterProps
): ReactNode | undefined {
    const attributeType = getAttributeType(type);
    return attributeType.formatValue(props);
}
