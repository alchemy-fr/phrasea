import React from 'react';
import {WidgetProps} from '../types';
import {
    FormControl,
    InputLabel,
    Select,
    MenuItem,
    FormHelperText,
} from '@mui/material';
import {FormFieldErrors} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';

interface ChoiceWidgetProps extends WidgetProps {
    multiple?: boolean;
}

const ChoiceWidget: React.FC<ChoiceWidgetProps> = ({
    usedFormSubmit: {
        register,
        formState: {errors},
    },
    schema,
    label,
    fieldName,
    required,
    multiple,
}) => {
    const {t} = useTranslation();
    const options = schema.enum ?? [];
    const optionNames = schema.enum_titles || options;

    return (
        <FormControl
            fullWidth
            required={required}
            error={!!errors?.[fieldName]}
        >
            <InputLabel id={`label-${fieldName}`}>{label}</InputLabel>
            <Select
                labelId={`label-${fieldName}`}
                id={`field-${fieldName}`}
                label={label}
                multiple={!!multiple}
                defaultValue={multiple ? [] : ''}
                {...register(fieldName)}
            >
                {schema.defaultValue !== false && (
                    <MenuItem value="">
                        <em>
                            {schema.defaultValue ||
                                schema.placeholder ||
                                t('form.selectPlaceholder', 'Choose an option')}
                        </em>
                    </MenuItem>
                )}
                {options.map((value: string, idx: number) => (
                    <MenuItem key={value} value={value}>
                        {optionNames[idx]}
                    </MenuItem>
                ))}
            </Select>
            {schema.description && (
                <FormHelperText>{schema.description}</FormHelperText>
            )}
            <FormFieldErrors field={fieldName} errors={errors} />
        </FormControl>
    );
};

export default ChoiceWidget;
