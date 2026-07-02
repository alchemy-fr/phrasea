import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
} from './types';
import React from 'react';
import BaseType from './BaseType.tsx';
import {AssetStatus} from '../../../../../types.ts';
import AssetStatusSelect from '../../../../Form/AssetStatusSelect.tsx';
import AssetStatusChip from '../../../../Ui/AssetStatusChip.tsx';

export default class AssetStatusType
    extends BaseType
    implements AttributeTypeInstance<AssetStatus>
{
    renderWidget({
        value,
        onChange,
        disabled,
    }: AttributeWidgetProps<AssetStatus>): React.ReactNode {
        return (
            <AssetStatusSelect
                value={value?.toString()}
                onChange={(newValue: any) => {
                    onChange(newValue?.value);
                }}
                disabled={disabled}
            />
        );
    }

    normalize(value: AssetStatus | undefined): string | undefined {
        return value?.toString();
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return <AssetStatusChip status={value} />;
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.toString();
    }
}
