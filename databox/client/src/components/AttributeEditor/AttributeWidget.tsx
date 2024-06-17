import React, {useCallback, useEffect, useState} from 'react';
import {getAttributeType} from '../Media/Asset/Attribute/types';

type Props<T> = {
    id: string;
    type: string;
    name: string;
    value: T;
    disabled: boolean;
    required: boolean;
    indeterminate?: boolean;
    readOnly?: boolean;
    autoFocus?: boolean;
    isRtl: boolean;
    onChange: (value: T | undefined) => void;
};

export default function AttributeWidget<T = string>({
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
}: Props<T>) {
    const denormalizeInputValue = (
        initialValue: T | undefined
    ) => undefined !== initialValue ? widget.denormalize(initialValue) : initialValue;

    const widget = getAttributeType(type);
    const [value, setValue] = useState<T | undefined>(
        denormalizeInputValue(initialValue)
    );
    const timeoutRef = React.useRef<ReturnType<typeof setTimeout>>();

    useEffect(() => {
        setValue(denormalizeInputValue(initialValue));
        // eslint-disable-next-line
    }, [initialValue]);

    const changeHandler = useCallback(
        (newValue: any) => {
            setValue(newValue);

            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }

            timeoutRef.current = setTimeout(() => onChange(newValue), 10);

            // eslint-disable-next-line
        },
        [onChange, setValue, value]
    );

    return (
        <>
            {widget.renderWidget({
                value: value || undefined,
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
    );
}
