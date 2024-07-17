import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
} from './types';
import React from 'react';
import {FormLabel} from '@mui/material';
import {AttributeEntity} from '../../../../../types.ts';
import AttributeEntitySelect from '../../../../Form/AttributeEntitySelect.tsx';
import BaseType from './BaseType.tsx';

export default class AttributeEntityType
    extends BaseType
    implements AttributeTypeInstance<AttributeEntity>
{
    renderWidget({
        value,
        name,
        onChange,
        id,
        readOnly,
        disabled,
    }: AttributeWidgetProps<AttributeEntity>): React.ReactNode {
        return (
            <>
                <FormLabel>{name}</FormLabel>
                <AttributeEntitySelect
                    id={id}
                    multiple={false}
                    name={name}
                    disabled={readOnly || disabled}
                    value={value}
                    onChange={newValue => {
                        onChange(
                            (newValue || undefined) as
                                | AttributeEntity
                                | undefined
                        );
                    }}
                />
            </>
        );
    }

    normalize(value: AttributeEntity | undefined): string | undefined {
        return value?.value;
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return <>{value?.label}</>;
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.label;
    }
}
