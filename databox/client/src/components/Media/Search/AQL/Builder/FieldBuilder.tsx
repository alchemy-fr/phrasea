import {RawType} from '../aqlTypes.ts';
import {TextField} from '@mui/material';
import React from 'react';
import {FieldWidget} from "../../../../../types.ts";
import {hasProp} from "../../../../../lib/utils.ts";

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
                console.log('v', v);
                setValue(v);
                if (typeof v === 'object' && hasProp<{value: string}>(v, 'value')) {
                    onChange(v.value);
                } else {
                    onChange(v);
                }
            },
            placeholder: label,
        });
    }

    return (
        <TextField
            type={rawType === RawType.Date ? 'datetime-local' : 'text'}
            name={name}
            label={label}
            value={value}
            onBlur={() => onChange(value)}
            onChange={e => {
                setValue(e.target.value);
            }}
            fullWidth={true}
        />
    );
}
