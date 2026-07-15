import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
} from './types';
import React from 'react';
import BaseType from './BaseType.tsx';
import PrivacyWidget from '../../../../Form/PrivacyWidget.tsx';
import PrivacyChip from '../../../../Ui/PrivacyChip.tsx';
import {Privacy} from '../../../../../api/privacy.ts';
import {getPrivacyTranslations} from '../../../../../translations/privacyTranslations.ts';
import {isNotNull} from '@alchemy/core';

export default class PrivacyType
    extends BaseType
    implements AttributeTypeInstance<Privacy>
{
    renderWidget({
        value,
        onChange,
        disabled,
    }: AttributeWidgetProps<Privacy>): React.ReactNode {
        return (
            <PrivacyWidget
                value={value}
                onChange={onChange}
                disabled={disabled}
            />
        );
    }

    normalize(value: Privacy | undefined): string | undefined {
        return value?.toString();
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return <PrivacyChip privacy={value} noAccess={false} />;
    }

    formatValueAsString({
        value,
        t,
    }: AttributeFormatterProps): string | undefined {
        const labels = getPrivacyTranslations(t);

        if (isNotNull(value)) {
            return labels[value.toString() as Privacy];
        }
    }
}
