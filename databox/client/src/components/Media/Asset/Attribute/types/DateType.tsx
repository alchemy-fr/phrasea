import React from 'react';
import {AttributeFormatterProps, AvailableFormat} from "./types";
import moment from "moment/moment";
import TextType from "./TextType";
import {TextFieldProps} from "@mui/material";

enum Formats {
    Short = 'short',
    Medium = 'medium',
    Relative = 'relative',
    Long = 'long',
}

export default class DateType extends TextType {
    formatValue(props: AttributeFormatterProps): React.ReactNode {
        return <>{this.format(props)}</>
    }

    formatValueAsString(props: AttributeFormatterProps): string | undefined {
        return this.format(props);
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
        ];
    }

    protected getFieldProps(): TextFieldProps {
        return {
            type: 'date',
            InputLabelProps: {
                shrink: true,
            }
        };
    }

    private format({value, format}: AttributeFormatterProps): string {
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
        }
    }
}
