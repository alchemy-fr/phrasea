import {AttributeFormatterProps, AvailableFormat} from './types';
import TextType from './TextType';
import React from 'react';
import {TextFieldProps} from '@mui/material';

enum Formats {
    Original = 'original',
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

    formatValue({
        value,
        format,
        highlight,
    }: AttributeFormatterProps): React.ReactNode {
        return this.formatValueAsString({value, format, highlight});
    }

    formatValueAsString({
        value,
        format,
    }: AttributeFormatterProps): string | undefined {
        console.log('value', value);
        if (typeof value !== 'number') {
            if (typeof value === 'string') {
                return value;
            }

            return undefined;
        }

        switch (format ?? this.getAvailableFormats()[0].name) {
            case Formats.Integer:
                return Math.round(value).toString();
            case Formats.Fixed:
                return value.toFixed(2);
            case Formats.Scientific:
                return value.toExponential(2);
            case Formats.Original:
            default:
                return value.toString();
        }
    }

    getAvailableFormats(): AvailableFormat[] {
        return [
            {
                name: Formats.Original,
                title: 'Original',
                example: this.formatValue({
                    value: 1234.5678,
                    format: Formats.Original,
                }),
            },
            {
                name: Formats.Integer,
                title: 'Integer',
                example: this.formatValue({
                    value: 1234.5678,
                    format: Formats.Integer,
                }),
            },
            {
                name: Formats.Fixed,
                title: 'Fixed (2 decimals)',
                example: this.formatValue({
                    value: 1234.5678,
                    format: Formats.Fixed,
                }),
            },
            {
                name: Formats.Scientific,
                title: 'Scientific',
                example: this.formatValue({
                    value: 1234.5678,
                    format: Formats.Scientific,
                }),
            },
        ];
    }
}
