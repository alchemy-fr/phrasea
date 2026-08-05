import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
    AvailableFormat,
} from './types';
import React from 'react';
import BaseType from './BaseType.tsx';
import PrivacyWidget from '../../../../Form/PrivacyWidget.tsx';
import PrivacyChip, {PrivacyIcon} from '../../../../Ui/PrivacyChip.tsx';
import {Privacy} from '../../../../../api/privacy.ts';
import {getPrivacyTranslations} from '../../../../../translations/privacyTranslations.ts';
import {isNotNull} from '@alchemy/core';

export enum PrivacyFormats {
    Full = 'full',
    // Icon-only rendering (lock icon with the label as tooltip).
    Short = 'short',
}

export default class PrivacyType
    extends BaseType
    implements AttributeTypeInstance<Privacy>
{
    public isRich = true;

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

    formatValue({value, format}: AttributeFormatterProps): React.ReactNode {
        if (PrivacyFormats.Short === format) {
            return <PrivacyIcon privacy={value} noAccess={false} />;
        }

        return <PrivacyChip privacy={value} noAccess={false} />;
    }

    getAvailableFormats(): AvailableFormat[] {
        return [
            {
                name: PrivacyFormats.Full,
                title: 'Full',
            },
            {
                name: PrivacyFormats.Short,
                title: 'Short',
            },
        ];
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
