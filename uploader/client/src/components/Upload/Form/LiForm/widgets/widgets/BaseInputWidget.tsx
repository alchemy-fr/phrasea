import React from 'react';
import {WidgetProps} from '../types.ts';
import {TextField, TextFieldProps} from '@mui/material';
import {FormFieldErrors} from '@alchemy/react-form';

type Props = {type: string} & WidgetProps & TextFieldProps;

export default function BaseInputWidget({
    usedFormSubmit: {
        register,
        formState: {errors},
    },
    schema,
    label,
    fieldName,
    normalizer,
    required,
    type,
}: Props) {
    return (
        <>
            <TextField
                fullWidth
                label={label}
                error={!!errors?.[fieldName]}
                required={required}
                placeholder={schema.defaultValue}
                helperText={schema.description}
                type={type}
                {...register(fieldName, {setValueAs: normalizer})}
            />
            <FormFieldErrors field={fieldName} errors={errors} />
        </>
    );
}
