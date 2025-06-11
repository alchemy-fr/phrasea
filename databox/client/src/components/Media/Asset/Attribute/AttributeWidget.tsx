import React, {useCallback, useEffect, useState} from 'react';
import {AttrValue, createNewValue} from './AttributesEditor';
import {getAttributeType} from './types';
import {AttributeWidgetOptions} from './types/types';
import {AttributeDefinition, EntityList} from '../../../../types.ts';
import {AttributeType} from '../../../../api/attributes.ts';

type Props = {
    id: string;
    type: AttributeType;
    name: string;
    value: AttrValue<string | number> | undefined;
    disabled: boolean;
    required: boolean;
    indeterminate?: boolean;
    readOnly?: boolean;
    autoFocus?: boolean;
    isRtl: boolean;
    onChange: (value: AttrValue<string | number>) => void;
    options: AttributeWidgetOptions;
};

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
    options,
}: Props) {
    const denormalizeInputValue = (
        initialValue: AttrValue<string | number> | undefined
    ) =>
        initialValue
            ? {
                  ...initialValue,
                  value: widget.denormalize(initialValue.value),
              }
            : initialValue;

    const widget = getAttributeType(type);
    const [value, setValue] = useState<AttrValue<string | number> | undefined>(
        denormalizeInputValue(initialValue)
    );
    const timeoutRef = React.useRef<ReturnType<typeof setTimeout>>();

    useEffect(() => {
        setValue(denormalizeInputValue(initialValue));
        // eslint-disable-next-line
    }, [initialValue?.id]);

    const changeHandler = useCallback(
        (newValue: any) => {
            const nv: AttrValue<string | number> = {
                ...(value || createNewValue(type)),
            };
            nv.value = newValue;
            setValue(nv);

            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }

            timeoutRef.current = setTimeout(() => onChange(nv), 10);

            // eslint-disable-next-line
        },
        [onChange, setValue, value]
    );

    return (
        <>
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
                options,
            })}
        </>
    );
}

export function createWidgetOptionsFromDefinition(
    definition: AttributeDefinition
): AttributeWidgetOptions {
    return {
        list: (definition.entityList as EntityList | undefined)?.id,
    };
}
