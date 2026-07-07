import {RawType} from '../aqlTypes.ts';
import {TextField, TextFieldProps} from '@mui/material';
import React from 'react';
import {AttributeType} from '../../../../../api/types.ts';
import {AttributeWidgetOptions} from '../../../Asset/Attribute/types/types';
import {getAttributeType} from '../../../Asset/Attribute/types/getAttributeType.ts';

type Props = {
    type?: AttributeType;
    widgetOptions?: AttributeWidgetOptions;
    rawType: RawType | undefined;
    value: string;
    name: string;
    label: string;
    onChange: (value: string) => void;
};

export type {Props as FieldBuilderProps};

export default function FieldBuilder({
    type,
    widgetOptions,
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

    if (type) {
        const attributeType = getAttributeType(type ?? AttributeType.Text);

        return attributeType.renderWidget({
            id: 'f-' + name,
            value,
            label,
            labelAlreadyRendered: true,
            onChange: (v: any) => {
                setValue(v);
                onChange(v);
            },
            options: widgetOptions ?? {},
        });
    }

    const extraProps: Partial<TextFieldProps> = {};

    let inputType = 'text';
    if (rawType === RawType.Date) {
        inputType = 'date';
    } else if (rawType === RawType.DateTime) {
        inputType = 'datetime-local';
        extraProps.InputProps = {
            inputProps: {
                step: 1,
            },
        };
    }

    return (
        <TextField
            type={inputType}
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
