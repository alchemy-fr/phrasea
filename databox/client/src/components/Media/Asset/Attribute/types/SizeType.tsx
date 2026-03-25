import {
    AttributeFormatterOptions,
    AttributeFormatterProps,
    AvailableFormat,
} from './types';
import TextType from './TextType';
import React from 'react';
import {TextFieldProps} from '@mui/material';

enum Formats {
    Original = 'original',
    Humanized = 'humanized',
}

export default class DurationType extends TextType {
    getFieldProps(): TextFieldProps {
        return {
            ...super.getFieldProps(),
            type: 'number',
        };
    }

    formatValue({
        value,
        format,
        ...options
    }: AttributeFormatterProps): React.ReactNode {
        const units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        const u = Math.floor(Math.log2(value) / 10);
        const v = Math.round(100 * (value / Math.pow(1024, u))) / 100.0;
        console.log(u, units[u]);
        switch (format ?? this.getAvailableFormats(options)[0].name) {
            case Formats.Humanized:
                return v.toLocaleString(options.uiLocale) + ' ' + units[u];
            case Formats.Original:
            default:
                return value.toString(); // + " Bytes";
        }
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        if (typeof value !== 'number') {
            if (typeof value === 'string') {
                return value;
            }
            return undefined;
        }
        return value?.toString();
    }

    getAvailableFormats(options: AttributeFormatterOptions): AvailableFormat[] {
        return [
            {
                name: Formats.Original,
                title: 'Original',
            },
            {
                name: Formats.Humanized,
                title: 'Humanized',
            },
        ].map(f => ({
            ...f,
            example: this.formatValue({
                ...options,
                value: 1234,
                format: f.name,
            }),
        }));
    }
}
