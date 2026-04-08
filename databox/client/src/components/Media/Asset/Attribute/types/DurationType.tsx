import {
    AttributeFormatterOptions,
    AttributeFormatterProps,
    AvailableFormat,
} from './types';
import moment from 'moment';
import NumberType from './NumberType.tsx';

enum Formats {
    Original = 'original',
    Formatted = 'formatted',
    Compact = 'compact',
    Humanized = 'humanized',
}

type DurationValues = {
    years?: string;
    months?: string;
    days?: string;
    hours?: string;
    minutes?: string;
    seconds?: string;
    years_padded?: string;
    months_padded?: string;
    days_padded?: string;
    hours_padded?: string;
    minutes_padded?: string;
    seconds_padded?: string;
};

export default class DurationType extends NumberType {
    formatValueAsString({
        value: v,
        format,
        ...options
    }: AttributeFormatterProps): string | undefined {
        const value = this.normalizeNumber(v);
        if (undefined === value) {
            return;
        }

        const t = options.t;
        const getFormat = {
            formated: (values: DurationValues): string => {
                if (values.years) {
                    return t('attribute.type.duration.years', {
                        defaultValue:
                            '{{years}} years, {{months}} months, {{days}} days, {{hours}} hours, {{minutes}} minutes, {{seconds}} seconds',
                        ...values,
                    });
                }
                if (values.months) {
                    return t('attribute.type.duration.months', {
                        defaultValue:
                            '{{months}} months, {{days}} days, {{hours}} hours, {{minutes}} minutes, {{seconds}} seconds',
                        ...values,
                    });
                }
                if (values.days) {
                    return t('attribute.type.duration.days', {
                        defaultValue:
                            '{{days}} days, {{hours}} hours, {{minutes}} minutes, {{seconds}} seconds',
                        ...values,
                    });
                }
                if (values.hours) {
                    return t('attribute.type.duration.hours', {
                        defaultValue:
                            '{{hours}} hours, {{minutes}} minutes, {{seconds}} seconds',
                        ...values,
                    });
                }
                if (values.minutes) {
                    return t('attribute.type.duration.minutes', {
                        defaultValue:
                            '{{minutes}} minutes, {{seconds}} seconds',
                        ...values,
                    });
                }
                if (values.seconds) {
                    return t('attribute.type.duration.seconds', {
                        defaultValue: '{{seconds}} seconds',
                        ...values,
                    });
                }
                return '';
            },
            compact: (values: DurationValues): string => {
                if (values.years) {
                    return t('attribute.type.duration.years_compact', {
                        defaultValue:
                            '{{years_padded}}y {{months_padded}}m {{days_padded}}d {{hours_padded}}:{{minutes_padded}}:{{seconds_padded}}',
                        ...values,
                    });
                }
                if (values.months) {
                    return t('attribute.type.duration.months_compact', {
                        defaultValue:
                            '{{months_padded}}m {{days_padded}}d {{hours_padded}}:{{minutes_padded}}:{{seconds_padded}}',
                        ...values,
                    });
                }
                if (values.days) {
                    return t('attribute.type.duration.days_compact', {
                        defaultValue:
                            '{{days_padded}}d {{hours_padded}}:{{minutes_padded}}:{{seconds_padded}}',
                        ...values,
                    });
                }
                return t('attribute.type.duration.time_compact', {
                    defaultValue:
                        '{{hours_padded}}:{{minutes_padded}}:{{seconds_padded}}',
                    ...values,
                });
            },
        };

        const d = moment.duration(value);
        const values: DurationValues = {};
        let fillRest = false;
        ['years', 'months', 'days', 'hours', 'minutes', 'seconds'].forEach(
            part => {
                let v = d.get(part as moment.unitOfTime.Base);
                let v_padded = v.toString().padStart(2, '0');
                if (part === 'seconds') {
                    v += d.milliseconds() / 1000;
                    v_padded +=
                        '.' +
                        d
                            .milliseconds()
                            .toPrecision(3)
                            .toString()
                            .padStart(3, '0');
                }
                if (v > 0 || fillRest) {
                    values[part as keyof DurationValues] = v.toString();
                    fillRest = true;
                }
                values[(part + '_padded') as keyof DurationValues] = v_padded;
            }
        );

        switch (format ?? this.getAvailableFormats(options)[0].name) {
            case Formats.Formatted:
                return getFormat['formated'](values);
            case Formats.Compact:
                return getFormat['compact'](values);
            case Formats.Humanized:
                return d.locale(options.uiLocale).humanize();
            case Formats.Original:
            default:
                return value.toString();
        }
    }

    getAvailableFormats(options: AttributeFormatterOptions): AvailableFormat[] {
        return [
            {
                name: Formats.Compact,
                title: 'Compact',
            },
            {
                name: Formats.Formatted,
                title: 'Formatted',
            },
            {
                name: Formats.Humanized,
                title: 'Humanized',
            },
            {
                name: Formats.Original,
                title: 'Original',
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
