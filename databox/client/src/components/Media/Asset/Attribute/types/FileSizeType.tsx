import {
    AttributeFormatterOptions,
    AttributeFormatterProps,
    AvailableFormat,
} from './types';
import NumberType from './NumberType.tsx';
import {formatFilesize} from '../../../../../lib/filesizeFormatter.ts';

enum Formats {
    Original = 'original',
    Humanized10 = 'humanized10',
    Humanized = 'humanized',
}

export default class FileSizeType extends NumberType {
    formatValueAsString({
        value: input,
        format,
        ...options
    }: AttributeFormatterProps): string | undefined {
        const value = this.normalizeNumber(input);
        if (undefined === value) {
            return;
        }

        const {t} = options;

        switch (format ?? this.getAvailableFormats(options)[0].name) {
            case Formats.Humanized:
                return formatFilesize(t, value, true, options.uiLocale);
            case Formats.Humanized10:
                return formatFilesize(t, value, false, options.uiLocale);
            case Formats.Original:
            default:
                return value.toString();
        }
    }

    getAvailableFormats(options: AttributeFormatterOptions): AvailableFormat[] {
        return [
            {
                name: Formats.Humanized,
                title: 'Humanized',
            },
            {
                name: Formats.Humanized10,
                title: 'Humanized (Base 10)',
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
