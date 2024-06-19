import {
    AttributeFormatterProps,
    AttributeWidgetProps,
    AvailableFormat,
} from './types';
import TextType from './TextType';
import {ColorBox, ColorPicker} from '@alchemy/react-form';
import {replaceHighlight} from '../Attributes';
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
        highlight,
    }: AttributeFormatterProps): React.ReactNode {
        switch (format ?? this.getAvailableFormats()[0].name) {
            default:
            case Formats.Box:
                return <>{value ? <ColorBox color={value} /> : value}</>;
            case Formats.Hex:
                return <>{replaceHighlight(highlight || value)}</>;
        }
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value.toString();
    }

    getAvailableFormats(): AvailableFormat[] {
        return [
            {
                name: Formats.Box,
                title: 'Box',
            },
            {
                name: Formats.Hex,
                title: 'Hexadecimal',
            },
        ];
    }
}
