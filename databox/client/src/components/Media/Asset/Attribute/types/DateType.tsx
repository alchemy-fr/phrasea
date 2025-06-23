import {AttributeFormatterProps, AvailableFormat} from './types';
import moment from 'moment/moment';
import TextType from './TextType';
import {TextFieldProps} from '@mui/material';
import React from 'react';

enum Formats {
    Short = 'short',
    Medium = 'medium',
    Relative = 'relative',
    Long = 'long',
    Iso = 'iso',
}

export {Formats as DateFormats};

export default class DateType extends TextType {
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

    getAvailableFormats(): AvailableFormat[] {
        return [
            {
                name: Formats.Medium,
                title: 'Medium',
            },
            {
                name: Formats.Short,
                title: 'Short',
            },
            {
                name: Formats.Long,
                title: 'Long',
            },
            {
                name: Formats.Relative,
                title: 'Relative',
            },
            {
                name: Formats.Iso,
                title: 'ISO',
            },
        ].map(f => ({
            ...f,
            example: this.formatValue({
                value: '2023-01-01T00:00:00Z',
                format: f.name,
            }),
        }));
    }

    public getFieldProps(): TextFieldProps {
        return {
            type: 'date',
            InputLabelProps: {
                shrink: true,
            },
        };
    }

    protected format({value, format}: AttributeFormatterProps): string {
        if (!value) {
            return '';
        }

        const m = moment(typeof value === 'number' ? value * 1000 : value);

        switch (format ?? this.getAvailableFormats()[0].name) {
            case Formats.Short:
                return m.format('ll');
            default:
            case Formats.Medium:
                return m.format('L');
            case Formats.Relative:
                return m.fromNow();
            case Formats.Long:
                return m.format('LLLL');
            case Formats.Iso:
                return m.format();
        }
    }
}
