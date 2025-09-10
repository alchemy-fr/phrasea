import {
    AttributeFormatterOptions,
    AttributeFormatterProps,
    AttributeWidgetProps,
    AvailableFormat,
} from './types';
import TextType from './TextType';
import {ColorBox, ColorPicker} from '@alchemy/react-form';
import {replaceHighlight} from '../AttributeHighlights';
import React from 'react';

enum Formats {
    Box = 'box',
    Hex = 'hex',
}

export default class ColorType extends TextType {
    renderWidget({
        value,
        onChange,
        disabled,
        name,
        readOnly,
    }: AttributeWidgetProps<string>): React.ReactNode {
        return (
            <ColorPicker
                color={value}
                onChange={onChange}
                label={name}
                readOnly={readOnly}
                disabled={disabled}
            />
        );
    }

    formatValue({
        value,
        format,
        ...options
    }: AttributeFormatterProps): React.ReactNode {
        switch (format ?? this.getAvailableFormats(options)[0].name) {
            default:
            case Formats.Box:
                return <>{value ? <ColorBox color={value} /> : value}</>;
            case Formats.Hex:
                return <>{replaceHighlight(options.highlight || value)}</>;
        }
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value.toString();
    }

    getAvailableFormats(options: AttributeFormatterOptions): AvailableFormat[] {
        return [
            {
                name: Formats.Box,
                title: 'Box',
                example: this.formatValue({
                    ...options,
                    value: '#FF0000',
                    format: Formats.Box,
                }),
            },
            {
                name: Formats.Hex,
                title: 'Hexadecimal',
                example: this.formatValue({
                    ...options,
                    value: '#FF0000',
                    format: Formats.Hex,
                }),
            },
        ];
    }
}
