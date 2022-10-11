import React, {ChangeEvent, useCallback, useEffect, useState} from "react";
import {TextField} from "@mui/material";
import {AttrValue, createNewValue} from "./AttributesEditor";

type Props = {
    id: string;
    type: string;
    name: string;
    value: AttrValue<string | number> | undefined;
    disabled: boolean;
    required: boolean;
    indeterminate?: boolean;
    readOnly?: boolean;
    isRtl: boolean;
    onChange: (value: AttrValue<string | number>) => void;
}

export default function AttributeWidget({
                                            id,
                                            disabled,
                                            name,
                                            value: initialValue,
                                            onChange,
                                            isRtl,
                                            required,
                                            type,
                                            indeterminate,
                                            readOnly,
                                        }: Props) {
    const [value, setValue] = useState<AttrValue<string | number> | undefined>(initialValue);

    useEffect(() => {
        setValue(initialValue);
        // eslint-disable-next-line
    }, [initialValue?.id]);

    const changeHandler = useCallback((e: ChangeEvent<HTMLInputElement>) => {
        const nv: AttrValue<string | number> = {...(value || createNewValue(type))};
        nv.value = e.target.value;
        setValue(nv);
        setTimeout(() => onChange(nv), 10);
        // eslint-disable-next-line
    }, [onChange, setValue]);

    switch (type) {
        default:
        case 'text':
        case 'textarea':
            const isMultiline = 'textarea' === type;
            return <TextField
                id={id}
                inputProps={{
                    readOnly,
                    style: readOnly ? {
                        cursor: 'not-allowed',
                    } : undefined,
                }}
                fullWidth
                rows={isMultiline ? 3 : undefined}
                multiline={isMultiline}
                disabled={readOnly || disabled}
                InputLabelProps={{ shrink: true }}
                label={name}
                onChange={changeHandler}
                value={value ? value.value : ''}
                required={required}
                autoFocus={true}
                style={{
                    direction: isRtl ? 'rtl' : undefined,
                    cursor: 'not-allowed',
                }}
                placeholder={indeterminate ? '[multiple values]' : (value ? value.fallbackValue : '')}
            />
    }
}
