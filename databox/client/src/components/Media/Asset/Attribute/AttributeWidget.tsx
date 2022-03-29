import React, {ChangeEvent, useCallback, useState} from "react";
import {TextField} from "@mui/material";

type Props = {
    id: string;
    type: string;
    name?: string;
    value: any;
    disabled: boolean;
    onChange: (value: any) => void;
}

export default function AttributeWidget({
                                            id,
                                            disabled,
                                            name,
                                            value: initialValue,
                                            onChange,
                                            type,
                                        }: Props) {
    const [value, setValue] = useState<any>(initialValue);

    const changeHandler = useCallback((e: ChangeEvent<HTMLInputElement>) => {
        const {value} = e.target;
        onChange(value);
        setValue(value);
    }, [onChange, setValue]);

    switch (type) {
        default:
        case 'text':
            return <TextField
                id={id}
                fullWidth
                disabled={disabled}
                label={name}
                onChange={changeHandler}
                value={value}
            />
    }
}
