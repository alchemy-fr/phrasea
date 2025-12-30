import React, {useState} from 'react';
import {WidgetProps} from '../types';
import {
    Box,
    FormControl,
    InputLabel,
    Select,
    MenuItem,
    FormHelperText,
} from '@mui/material';
import {FormFieldErrors} from '@alchemy/react-form';

const OneOfChoiceWidget: React.FC<WidgetProps> = ({
    usedFormSubmit: {
        formState: {errors},
    },
    schema,
    label,
    fieldName,
    required,
}) => {
    const options = schema.oneOf || [];
    const [choice, setChoice] = useState(0);

    const handleChange = (event: any) => {
        setChoice(Number(event.target.value));
    };

    return (
        <Box mb={2}>
            <FormControl
                fullWidth
                required={required}
                error={!!errors?.[fieldName]}
                margin="normal"
            >
                <InputLabel id={`label-${fieldName}`}>
                    {schema.title || label}
                </InputLabel>
                <Select
                    labelId={`label-${fieldName}`}
                    id={`field-${fieldName}`}
                    value={choice}
                    onChange={handleChange}
                >
                    {options.map((item: any, idx: number) => (
                        <MenuItem key={idx} value={idx}>
                            {item.title || idx}
                        </MenuItem>
                    ))}
                </Select>
                {schema.description && (
                    <FormHelperText>{schema.description}</FormHelperText>
                )}
                <FormFieldErrors field={fieldName} errors={errors} />
            </FormControl>
            {/* Render the selected option's fields here, if needed */}
        </Box>
    );
};

export default OneOfChoiceWidget;
