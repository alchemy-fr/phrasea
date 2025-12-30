import React from 'react';
import {WidgetProps} from '../types';
import {
    Checkbox,
    FormControlLabel,
    FormHelperText,
    FormGroup,
} from '@mui/material';
import {FormFieldErrors} from '@alchemy/react-form';

const CheckboxWidget: React.FC<WidgetProps> = ({
    usedFormSubmit: {
        register,
        formState: {errors},
    },
    schema,
    label,
    fieldName,
    required,
}) => {
    return (
        <FormGroup>
            <FormControlLabel
                control={
                    <Checkbox
                        {...register(fieldName)}
                        required={required}
                        id={`field-${fieldName}`}
                    />
                }
                label={label}
            />
            {schema.description && (
                <FormHelperText>{schema.description}</FormHelperText>
            )}
            <FormFieldErrors field={fieldName} errors={errors} />
        </FormGroup>
    );
};

export default CheckboxWidget;
