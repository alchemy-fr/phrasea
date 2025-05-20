import React, {useCallback, useEffect, useState} from 'react';
import {getAttributeType} from '../Media/Asset/Attribute/types';
import {AttributeWidgetProps} from '../Media/Asset/Attribute/types/types';
import {AttributeType} from "../../api/attributes.ts";

type Props<T> = {
    type: AttributeType;
} & AttributeWidgetProps<T>;

export default function AttributeWidget<T = string>({
    value: initialValue,
    onChange,
    type,
    ...props
}: Props<T>) {
    const denormalizeInputValue = (initialValue: T | undefined) =>
        undefined !== initialValue
            ? widget.denormalize(initialValue)
            : initialValue;

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
                onChange: changeHandler,
                ...props,
            })}
        </>
    );
}
