import {
    AttributeFormatterOptions,
    AttributeFormatterProps,
    AvailableFormat,
} from './types';
import TextType from './TextType';
import React from 'react';
import {TextFieldProps} from '@mui/material';
import moment from 'moment';
import {getI18n} from 'react-i18next';

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
        const i18n = getI18n();
        const d = moment.duration(value);
        let s = '';
        switch (format ?? this.getAvailableFormats(options)[0].name) {
            case Formats.Formatted:
                if (d.years() > 0) {
                    s +=
                        d.years() +
                        ' ' +
                        i18n.t('attribute.type.duration.years', 'years') +
                        ' ';
                }
                if (d.months() > 0) {
                    s +=
                        d.months() +
                        ' ' +
                        i18n.t('attribute.type.duration.months', 'months') +
                        ' ';
                }
                if (d.days() > 0) {
                    s +=
                        d.days() +
                        ' ' +
                        i18n.t('attribute.type.duration.days', 'days') +
                        ' ';
                }
                if (d.hours() > 0) {
                    s +=
                        d.hours() +
                        ' ' +
                        i18n.t('attribute.type.duration.hours', 'hours') +
                        ' ';
                }
                if (d.minutes() > 0) {
                    s +=
                        d.minutes() +
                        ' ' +
                        i18n.t('attribute.type.duration.minutes', 'minutes') +
                        ' ';
                }
                if (d.seconds() > 0 || d.milliseconds() > 0) {
                    s +=
                        (d.seconds() + d.milliseconds() / 1000).toLocaleString(
                            options.uiLocale
                        ) +
                        ' ' +
                        i18n.t('attribute.type.duration.seconds', 'seconds') +
                        ' ';
                }
                return s.trim();
            case Formats.Humanized:
                return d.locale(options.uiLocale).humanize();
            case Formats.Original:
            default:
                return value.toString(); // + ' ' + i18n.t('attribute.type.duration.milliseconds', 'milliseconds');
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
                value: 1234.5678,
                format: f.name,
            }),
        }));
    }
}
