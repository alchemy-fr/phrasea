import React, {useCallback, useEffect, useState} from 'react';
import {AttrValue} from './AttributesEditor';
import {getAttributeType} from './types';
import {AttributeWidgetProps} from './attributeTypes.ts';
import {createNewValue} from './values.ts';
import {AttributeType} from '../../../../api/types.ts';
import {FormHelperText} from '@mui/material';
import {useTranslation} from 'react-i18next';
import Button from '@mui/material/Button';

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
    const {t} = useTranslation();
    const [forceValid, setForceValid] = useState(false);
    const denormalizeInputValue = (
        initialValue: AttrValue<string | number> | undefined
    ) =>
        initialValue
            ? {
                  ...initialValue,
                  value: widget.denormalize(initialValue.value),
              }
            : initialValue;

    const wasInvalid = Boolean(initialValue?.invalid);

    const wasTextWidget = [
        AttributeType.Text,
        AttributeType.Html,
        AttributeType.Code,
        AttributeType.Textarea,
    ].includes(type);

    const widget =
        wasInvalid && !forceValid && !wasTextWidget
            ? getAttributeType(AttributeType.Text)
            : getAttributeType(type);
    const [value, setValue] = useState<AttrValue<string | number> | undefined>(
        denormalizeInputValue(initialValue)
    );
    const timeoutRef = React.useRef<ReturnType<typeof setTimeout>>();

    useEffect(() => {
        setValue(denormalizeInputValue(initialValue));
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
            {wasInvalid && !forceValid && (
                <FormHelperText
                    sx={{
                        color: 'error.main',
                    }}
                >
                    {t(
                        'attribute.editor.invalid_valid',
                        'Invalid value. Please correct it!'
                    )}
                    {!wasTextWidget && (
                        <Button
                            sx={{
                                ml: 1,
                            }}
                            onClick={() => setForceValid(true)}
                        >
                            {t('attribute.editor.correct_invalid', 'Correct')}
                        </Button>
                    )}
                </FormHelperText>
            )}
        </>
    );
}
