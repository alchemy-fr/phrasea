import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
    AvailableFormat,
} from './types';
import {Checkbox, Chip, FormControlLabel, TextFieldProps} from '@mui/material';
import BaseType from './BaseType';
import React from 'react';

enum Formats {
    Thumbs = 'thumbs',
    Label = 'label',
    Binary = 'binary',
    TrueFalse = 'true_false',
}

export default class BooleanType
    extends BaseType
    implements AttributeTypeInstance<boolean>
{
    formatValue({value, format}: AttributeFormatterProps): React.ReactNode {
        if (false !== value && true !== value) {
            return;
        }

        switch (format ?? this.getDefaultFormat()) {
            default:
            case Formats.Label:
                return (
                    <Chip
                        color={value ? 'success' : 'error'}
                        label={value ? 'Yes' : 'No'}
                    />
                );
            case Formats.Binary:
                return <>{value ? '1' : '0'}</>;
            case Formats.Thumbs:
                return <>{value ? '👍' : '👎'}</>;
            case Formats.TrueFalse:
                return <>{value ? 'true' : 'false'}</>;
        }
    }

    renderWidget({
        value,
        onChange,
        name,
        inputRef,
    }: AttributeWidgetProps<boolean>): React.ReactNode {
        return (
            <FormControlLabel
                control={
                    <Checkbox
                        inputRef={inputRef}
                        checked={value ?? false}
                        indeterminate={value === undefined}
                        onChange={(_e, checked) => onChange(checked)}
                    />
                }
                label={name}
            />
        );
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        if (true === value) {
            return 'Yes';
        } else if (false === value) {
            return 'No';
        }

        return '';
    }

    getAvailableFormats(): AvailableFormat[] {
        return [
            {
                name: Formats.Label,
                title: 'Label',
            },
            {
                name: Formats.Binary,
                title: 'Binary',
            },
            {
                name: Formats.Thumbs,
                title: 'Thumbs',
            },
            {
                name: Formats.TrueFalse,
                title: 'True/False',
            },
        ];
    }

    public getFieldProps(): TextFieldProps {
        return {
            type: 'date',
            InputLabelProps: {
                shrink: true,
            },
        };
    }
}
