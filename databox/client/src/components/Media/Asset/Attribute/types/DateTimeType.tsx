import {
    AttributeFormatterOptions,
    AttributeFormatterProps,
    AvailableFormat,
} from './types';
import {TextFieldProps} from '@mui/material';
import React from 'react';
import TextType from './TextType.tsx';
import moment from 'moment';

export enum DateFormats {
    Short = 'short',
    Medium = 'medium',
    Relative = 'relative',
    Long = 'long',
    Iso = 'iso',
}

export default class DateTimeType extends TextType {
    formatValue(props: AttributeFormatterProps): React.ReactNode {
        return <>{this.format(props)}</>;
    }

    formatValueAsString(props: AttributeFormatterProps): string | undefined {
        return this.format(props);
    }

    denormalize(value: string | undefined): string | undefined {
        if (value) {
            return moment(value)
                .local(false)
                .format()
                .replace(/(Z|[+-]\d{2}:\d{2})$/, '');
        }

        return value;
    }

    normalize(value: string | undefined): string | undefined {
        if (value) {
            value = moment(value).local(true).format();
        }

        return value;
    }

    getAvailableFormats(options: AttributeFormatterOptions): AvailableFormat[] {
        return [
            {
                name: DateFormats.Medium,
                title: 'Medium',
            },
            {
                name: DateFormats.Short,
                title: 'Short',
            },
            {
                name: DateFormats.Long,
                title: 'Long',
            },
            {
                name: DateFormats.Relative,
                title: 'Relative',
            },
            {
                name: DateFormats.Iso,
                title: 'ISO',
            },
        ].map(f => ({
            ...f,
            example: this.formatValue({
                ...options,
                value: '2023-01-01T00:00:00Z',
                format: f.name,
            }),
        }));
    }

    public getFieldProps(): TextFieldProps {
        return {
            type: 'datetime-local',
            InputProps: {
                inputProps: {
                    step: 1,
                },
            },
            InputLabelProps: {
                shrink: true,
            },
        };
    }

    protected format({
        value,
        format,
        ...options
    }: AttributeFormatterProps): string {
        if (!value) {
            return '';
        }

        const m = moment(typeof value === 'number' ? value * 1000 : value);

        switch (format ?? this.getAvailableFormats(options)[0].name) {
            case DateFormats.Short:
                return m.format('L LT');
            default:
            case DateFormats.Medium:
                return m.format('lll');
            case DateFormats.Relative:
                return m.fromNow();
            case DateFormats.Long:
                return m.format('LLLL');
            case DateFormats.Iso:
                return m.format();
        }
    }
}
