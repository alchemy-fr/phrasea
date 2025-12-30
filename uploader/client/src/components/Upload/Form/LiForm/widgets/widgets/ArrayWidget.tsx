import React from 'react';
import {WidgetProps} from '../types';
import {Box, Button, IconButton, Typography} from '@mui/material';
import KeyboardArrowUpIcon from '@mui/icons-material/KeyboardArrowUp';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';
import DeleteIcon from '@mui/icons-material/Delete';
import {useFieldArray} from 'react-hook-form';
import ChoiceWidget from './ChoiceWidget';
import {UploadFormData} from '../../../../../../types.ts';
import {UseFormSubmitReturn} from '@alchemy/api';

const renderArrayFields = (
    usedFormSubmit: UseFormSubmitReturn<UploadFormData>,
    fields: any[],
    schema: any,
    fieldName: string,
    remove: (idx: number) => void,
    swap: (a: number, b: number) => void,
    context: any
) => {
    return fields.map((item, idx) => (
        <Box key={item.id} mb={2}>
            <Box display="flex" justifyContent="flex-end" gap={1}>
                {idx !== fields.length - 1 && fields.length > 1 && (
                    <IconButton
                        onClick={() => swap(idx, idx + 1)}
                        size="small"
                        color="primary"
                    >
                        <KeyboardArrowDownIcon />
                    </IconButton>
                )}
                {idx !== 0 && fields.length > 1 && (
                    <IconButton
                        onClick={() => swap(idx, idx - 1)}
                        size="small"
                        color="primary"
                    >
                        <KeyboardArrowUpIcon />
                    </IconButton>
                )}
                <IconButton
                    onClick={() => remove(idx)}
                    size="small"
                    color="error"
                >
                    <DeleteIcon />
                </IconButton>
            </Box>
            {/* Render the field using the renderField logic, or a generic widget */}
            <ChoiceWidget
                {...{
                    usedFormSubmit,
                    fieldName: `${fieldName}.${idx}`,
                    schema: {...schema, showLabel: false},
                    context,
                }}
            />
        </Box>
    ));
};

const ArrayWidget: React.FC<WidgetProps> = ({
    usedFormSubmit,
    schema,
    label,
    fieldName,
    context,
}) => {
    const {control} = usedFormSubmit;
    const {fields, remove, append, swap} = useFieldArray({
        control,
        name: fieldName,
    });

    // If enum + uniqueItems, treat as multi-select
    if (schema.items?.enum && schema.uniqueItems) {
        return (
            <ChoiceWidget
                fieldName={fieldName}
                schema={schema.items}
                context={context}
                multiple
                usedFormSubmit={usedFormSubmit}
            />
        );
    }

    return (
        <Box mb={2}>
            <Typography variant="subtitle1" gutterBottom>
                {label}
            </Typography>
            {renderArrayFields(
                usedFormSubmit,
                fields,
                schema.items,
                fieldName,
                remove,
                swap,
                context
            )}
            <Button
                variant="contained"
                color="primary"
                onClick={() => append({})}
                sx={{mt: 1}}
            >
                Add
            </Button>
        </Box>
    );
};

export default ArrayWidget;
