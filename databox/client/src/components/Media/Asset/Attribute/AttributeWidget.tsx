import React, {useCallback, useEffect, useState} from "react";
import {AttrValue, createNewValue} from "./AttributesEditor";
import {getAttributeType} from "./types";

type Props = {
    id: string;
    type: string;
    name: string;
    value: AttrValue<string | number> | undefined;
    disabled: boolean;
    required: boolean;
    indeterminate?: boolean;
    readOnly?: boolean;
    autoFocus?: boolean;
    isRtl: boolean;
    onChange: (value: AttrValue<string | number>) => void;
}

export default function AttributeWidget({
    id,
    disabled,
    autoFocus,
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
    const timeoutRef = React.useRef<ReturnType<typeof setTimeout>>();

    useEffect(() => {
        setValue(initialValue);
        // eslint-disable-next-line
    }, [initialValue?.id]);

    const changeHandler = useCallback((newValue: any) => {
        const nv: AttrValue<string | number> = {...(value || createNewValue(type))};
        nv.value = newValue;
        setValue(nv);

        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
        }

        timeoutRef.current = setTimeout(() => onChange(nv), 10);

        // eslint-disable-next-line
    }, [onChange, setValue, value]);

    const widget = getAttributeType(type);

    return <>
        {widget.renderWidget({
            value: value ? value.value : undefined,
            isRtl,
            onChange: changeHandler,
            readOnly,
            id,
            name,
            required,
            indeterminate,
            autoFocus,
            disabled,
        })}
    </>
}
