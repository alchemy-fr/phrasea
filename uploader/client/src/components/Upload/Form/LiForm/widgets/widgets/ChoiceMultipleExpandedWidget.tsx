import React from 'react';
import {WidgetProps} from '../types';
import {Checkbox, FormControlLabel, FormGroup, InputLabel} from '@mui/material';
import {Controller} from 'react-hook-form';

const ChoiceMultipleExpandedWidget: React.FC<WidgetProps> = ({
    usedFormSubmit,
    schema,
    fieldName,
    label,
}) => {
    const options = schema.items?.enum ?? [];
    const optionNames = schema.items?.enum_titles || options;

    return (
        <>
            <InputLabel>{label || fieldName}</InputLabel>
            <Controller
                control={usedFormSubmit.control}
                name={fieldName}
                render={({field: {value, onChange}}) => (
                    <>
                        {options.map((item: string, idx: number) => (
                            <FormGroup key={idx}>
                                <FormControlLabel
                                    control={
                                        <Checkbox
                                            id={`${fieldName}-${item}`}
                                            onChange={() => {
                                                const newValue = Array.isArray(
                                                    value
                                                )
                                                    ? [...value]
                                                    : [];
                                                if (newValue.includes(item)) {
                                                    const index =
                                                        newValue.indexOf(item);
                                                    newValue.splice(index, 1);
                                                } else {
                                                    newValue.push(item);
                                                }
                                                onChange(newValue);
                                            }}
                                        />
                                    }
                                    label={optionNames[idx] || item}
                                />
                            </FormGroup>
                        ))}
                    </>
                )}
            />
        </>
    );
};

export default ChoiceMultipleExpandedWidget;
