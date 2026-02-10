import React, {useCallback, useEffect, useState} from 'react';
import {AttrValue} from './AttributesEditor';
import {getAttributeType} from './types';
import {AttributeWidgetOptions} from './types/types';
import {AttributeDefinition, EntityList} from '../../../../types.ts';
import {AttributeWidgetProps} from './attributeTypes.ts';
import {createNewValue} from './values.ts';

export default function AttributeWidget({
    id,
    disabled,
    autoFocus,
    label,
    value: initialValue,
    labelAlreadyRendered,
    onChange,
    isRtl,
    required,
    type,
    indeterminate,
    readOnly,
    options,
}: AttributeWidgetProps) {
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
                labelAlreadyRendered,
                readOnly,
                id,
                label,
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
