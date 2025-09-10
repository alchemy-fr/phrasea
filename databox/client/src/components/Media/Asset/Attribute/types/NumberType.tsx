import {
    AttributeFormatterOptions,
    AttributeFormatterProps,
    AvailableFormat,
} from './types';
import TextType from './TextType';
import React from 'react';
import {TextFieldProps} from '@mui/material';
import {formatNumber} from '../../../../../lib/numbers.ts';

enum Formats {
    Original = 'original',
    Formatted = 'formatted',
    Integer = 'integer',
    Fixed = 'fixed',
    Scientific = 'scientific',
}

export default class NumberType extends TextType {
    getFieldProps(): TextFieldProps {
        return {
            ...super.getFieldProps(),
            type: 'number',
        };
    }

    formatValue(props: AttributeFormatterProps): React.ReactNode {
        return this.formatValueAsString(props);
    }

    formatValueAsString({
        value,
        format,
        ...options
    }: AttributeFormatterProps): string | undefined {
        if (typeof value !== 'number') {
            if (typeof value === 'string') {
                return value;
            }

            return undefined;
        }

        switch (format ?? this.getAvailableFormats(options)[0].name) {
            case Formats.Integer:
                return Math.round(value).toString();
            case Formats.Formatted:
                return formatNumber(value, options.uiLocale);
            case Formats.Fixed:
                return value.toFixed(2);
            case Formats.Scientific:
                return value.toExponential(2);
            case Formats.Original:
            default:
                return value.toString();
        }
    }

    getAvailableFormats(options: AttributeFormatterOptions): AvailableFormat[] {
        return [
            {
                name: Formats.Original,
                title: 'Original',
            },
            {
                name: Formats.Integer,
                title: 'Integer',
            },
            {
                name: Formats.Formatted,
                title: 'Formatted',
            },
            {
                name: Formats.Fixed,
                title: 'Fixed (2 decimals)',
            },
            {
                name: Formats.Scientific,
                title: 'Scientific',
            },
        ].map(f => ({
            ...f,
            example: this.formatValue({
                ...options,
                value: 1234.5678,
                format: f.name,
            }),
        }));
    }
}
