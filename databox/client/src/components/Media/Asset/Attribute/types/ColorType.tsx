import React from 'react';
import {AttributeFormatterProps, AttributeWidgetProps, AvailableFormat} from "./types";
import TextType from "./TextType";
import ColorPicker, {ColorBox} from "../../../../Form/ColorPicker";

enum Formats {
    Box = 'box',
    Hex = 'hex',
}

export default class ColorType extends TextType {
    renderWidget({
                     value,
                     onChange,
                     id,
                     disabled,
                     name,
                     readOnly,
                 }: AttributeWidgetProps): React.ReactNode {
        return <ColorPicker
            color={value}
            onChange={onChange}
            label={name}
            readOnly={readOnly}
            disabled={disabled}
        />
    }

    formatValue({value, format}: AttributeFormatterProps): React.ReactNode {
        switch (format ?? this.getAvailableFormats()[0].name) {
            default:
            case Formats.Box:
                return <>{value ? <ColorBox color={value}/> : value}</>
            case Formats.Hex:
                return <>{value}</>
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
