import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
} from './types';
import React from 'react';
import {InputLabel} from '@mui/material';
import {AttributeEntity} from '../../../../../types.ts';
import AttributeEntitySelect, {
    AttributeEntityOption,
} from '../../../../Form/AttributeEntitySelect.tsx';
import BaseType from './BaseType.tsx';

export default class AttributeEntityType
    extends BaseType
    implements AttributeTypeInstance<AttributeEntity>
{
    renderWidget({
        labelAlreadyRendered,
        value,
        label,
        onChange,
        id,
        readOnly,
        disabled,
        options,
    }: AttributeWidgetProps<AttributeEntity>): React.ReactNode {
        return (
            <>
                {!labelAlreadyRendered && <InputLabel>{label}</InputLabel>}
                <AttributeEntitySelect
                    id={id}
                    multiple={false}
                    list={options.list}
                    disabled={readOnly || disabled}
                    value={value?.id}
                    onChange={newValue => {
                        onChange(
                            (
                                (newValue || undefined) as
                                    | AttributeEntityOption
                                    | undefined
                            )?.item
                        );
                    }}
                />
            </>
        );
    }

    normalize(value: AttributeEntity | undefined): string | undefined {
        return value?.id;
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return value?.value as React.ReactNode;
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.value;
    }
}
