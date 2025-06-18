import {RawType} from '../aqlTypes.ts';
import {TextField, TextFieldProps} from '@mui/material';
import React from 'react';
import {FieldWidget} from '../../../../../types.ts';
import {hasProp} from '../../../../../lib/utils.ts';

type Props = {
    widget?: FieldWidget;
    rawType: RawType | undefined;
    value: string;
    name: string;
    label: string;
    onChange: (value: string) => void;
};

export type {Props as FieldBuilderProps};

export default function FieldBuilder({
    widget,
    rawType,
    value: initialValue,
    onChange,
    name,
    label,
}: Props) {
    const [value, setValue] = React.useState(initialValue);

    React.useEffect(() => {
        setValue(initialValue);
    }, [initialValue]);

    if (widget) {
        return React.createElement(widget.component, {
            ...(widget.props ?? {}),
            name,
            value,
            onChange: (v: any) => {
                setValue(v);
                if (
                    typeof v === 'object' &&
                    hasProp<{value: string}>(v, 'value')
                ) {
                    onChange(v.value);
                } else {
                    onChange(v);
                }
            },
            placeholder: label,
        });
    }

    const extraProps: Partial<TextFieldProps> = {};

    let fieldType = 'text';
    if (rawType === RawType.Date) {
        fieldType = 'date';
    } else if (rawType === RawType.DateTime) {
        fieldType = 'datetime-local';
        extraProps.InputProps = {
            inputProps: {
                step: 1,
            },
        };
    }

    return (
        <TextField
            type={fieldType}
            name={name}
            label={label}
            value={value}
            onBlur={() => onChange(value)}
            onChange={e => {
                setValue(e.target.value);
            }}
            fullWidth={true}
            {...extraProps}
        />
    );
}
