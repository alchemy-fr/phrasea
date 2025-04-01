import {RawType} from "../aqlTypes.ts";
import {TextField} from "@mui/material";
import React from "react";

type Props = {
    rawType: RawType | undefined,
    value: string,
    name: string,
    label: string,
    onChange: (value: string) => void,
};

export type {Props as FieldBuilderProps};

export default function FieldBuilder({
    rawType,
    value,
    onChange,
    name,
    label,
}: Props) {
    return <TextField
        type={rawType === RawType.Date ? 'datetime-local' : 'text'}
            name={name}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            fullWidth={true}
            placeholder={label}
        />
}
