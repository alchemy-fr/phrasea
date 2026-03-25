import {
    AttributeFormatterOptions,
    AttributeFormatterProps,
    AvailableFormat,
} from './types';
import TextType from './TextType';
import React from 'react';
import {TextFieldProps} from '@mui/material';
import moment from 'moment';

enum Formats {
    Original = 'original',
    Formatted = 'formatted',
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
        const d = moment.duration(value);
        let nParts = 0;
        const defaultValue: Array<string> = [];
        let values = {};
        switch (format ?? this.getAvailableFormats(options)[0].name) {
            case Formats.Formatted:
                [
                    'years',
                    'months',
                    'days',
                    'hours',
                    'minutes',
                    'seconds',
                ].forEach(part => {
                    let v = d.get(part as moment.unitOfTime.Base);
                    if (part === 'seconds') {
                        v += d.milliseconds() / 1000;
                    }
                    if (nParts > 0 || v > 0) {
                        defaultValue.push('{{' + part + '}} ' + part);
                        values = {...values, [part]: v};
                        nParts++;
                    }
                });
                return options.t('attribute.type.duration.{{nParts}}_parts', {
                    defaultValue: defaultValue.join(', '),
                    ...values,
                });
            case Formats.Humanized:
                return d.locale(options.uiLocale).humanize();
            case Formats.Original:
            default:
                return value.toString();
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
                name: Formats.Formatted,
                title: 'Formatted',
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
