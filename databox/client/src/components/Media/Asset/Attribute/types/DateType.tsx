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

        switch (format ?? this.getAvailableFormats()[0].name) {
            case Formats.Short:
                return moment(value).format('ll');
            default:
            case Formats.Medium:
                return moment(value).format('L');
            case Formats.Relative:
                return moment(value).fromNow();
            case Formats.Long:
                return moment(value).format('LLLL');
        }
    }
}
