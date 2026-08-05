import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
} from './types';
import React from 'react';
import BaseType from './BaseType.tsx';
import {RenditionDefinition} from '../../../../../types.ts';
import {EntityName} from '../../../../../api/types.ts';
import RenditionDefinitionSelect from '../../../../Form/RenditionDefinitionSelect.tsx';

export default class RenditionDefinitionType
    extends BaseType
    implements AttributeTypeInstance<string>
{
    public entityIri = EntityName.RenditionDefinition;
    public isRich = true;

    renderWidget({
        value,
        onChange,
        disabled,
    }: AttributeWidgetProps<string>): React.ReactNode {
        return (
            <RenditionDefinitionSelect
                useIRI={false}
                value={value}
                onChange={newValue => {
                    onChange(newValue?.value);
                }}
                disabled={disabled}
            />
        );
    }

    normalize(value: RenditionDefinition | undefined): string | undefined {
        if (value && typeof value === 'string') {
            return value;
        }

        return value?.id;
    }

    formatValue(props: AttributeFormatterProps): React.ReactNode {
        return this.formatValueAsString(props);
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.displayName;
    }
}
