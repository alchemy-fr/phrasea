import {
    AttributeFormatterOptions,
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
    AvailableFormat,
} from './types';
import {Checkbox, Chip, FormControlLabel} from '@mui/material';
import BaseType from './BaseType';
import React from 'react';
import NullableBooleanWidget from '../../../../Form/NullableBooleanWidget.tsx';

enum Formats {
    Thumbs = 'thumbs',
    Label = 'label',
    Binary = 'binary',
    TrueFalse = 'true_false',
}

export default class BooleanType
    extends BaseType
    implements AttributeTypeInstance<boolean>
{
    formatValue({
        value,
        format,
        ...formatterOptions
    }: AttributeFormatterProps): React.ReactNode {
        if (false !== value && true !== value) {
            return;
        }

        switch (format ?? this.getDefaultFormat(formatterOptions)) {
            default:
            case Formats.Label:
                return (
                    <Chip
                        color={value ? 'success' : 'error'}
                        label={value ? 'Yes' : 'No'}
                    />
                );
            case Formats.Binary:
                return <>{value ? '1' : '0'}</>;
            case Formats.Thumbs:
                return <>{value ? '👍' : '👎'}</>;
            case Formats.TrueFalse:
                return <>{value ? 'true' : 'false'}</>;
        }
    }

    renderWidget({
        value,
        onChange,
        label,
        inputRef,
        required,
        readOnly,
        labelAlreadyRendered,
    }: AttributeWidgetProps<boolean>): React.ReactNode {
        if (!required) {
            return (
                <NullableBooleanWidget
                    value={value}
                    onChange={onChange}
                    label={!labelAlreadyRendered ? label : undefined}
                />
            );
        }

        return (
            <FormControlLabel
                control={
                    <Checkbox
                        inputRef={inputRef}
                        readOnly={readOnly}
                        checked={value ?? false}
                        indeterminate={value === undefined}
                        onChange={(_e, checked) => onChange(checked)}
                    />
                }
                label={label}
            />
        );
    }

    formatValueAsString({
        value,
        t,
    }: AttributeFormatterProps): string | undefined {
        if (true === value) {
            return t('attribute_type.boolean.yes.label', 'Yes');
        } else if (false === value) {
            return t('attribute_type.boolean.no.label', 'No');
        }

        return '';
    }

    getAvailableFormats(options: AttributeFormatterOptions): AvailableFormat[] {
        return [
            {
                name: Formats.Label,
                title: 'Label',
            },
            {
                name: Formats.Binary,
                title: 'Binary',
            },
            {
                name: Formats.Thumbs,
                title: 'Thumbs',
            },
            {
                name: Formats.TrueFalse,
                title: 'True/False',
            },
        ].map(f => ({
            ...f,
            example: this.formatValue({
                ...options,
                value: true,
                format: f.name,
            }),
        }));
    }
}
