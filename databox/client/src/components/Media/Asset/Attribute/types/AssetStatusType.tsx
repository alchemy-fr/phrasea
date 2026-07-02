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
import {getAssetStatusTranslations} from '../../../../../translations/assetStatusTranslations.ts';
import {isNotNull} from '@alchemy/core';

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

    formatValueAsString({
        value,
        t,
    }: AttributeFormatterProps): string | undefined {
        const labels = getAssetStatusTranslations(t);

        if (isNotNull(value)) {
            return labels[value.toString() as AssetStatus];
        }
    }
}
