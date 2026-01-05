import React from 'react';
import {WidgetProps} from '../types';
import {Box, Button, IconButton, InputLabel} from '@mui/material';
import {useTranslation} from 'react-i18next';
import KeyboardArrowUpIcon from '@mui/icons-material/KeyboardArrowUp';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';
import DeleteIcon from '@mui/icons-material/Delete';
import {FieldArrayWithId, useFieldArray} from 'react-hook-form';
import ChoiceWidget from './ChoiceWidget';
import {LiFormSchema, UploadFormData} from '../../../../../../types.ts';
import {UseFormSubmitReturn} from '@alchemy/api';
import {renderField} from '../../renderField.tsx';
import AddIcon from '@mui/icons-material/Add';

const renderArrayFields = ({
    usedFormSubmit,
    fields,
    schema,
    fieldName,
    remove,
    swap,
    context,
}: {
    usedFormSubmit: UseFormSubmitReturn<UploadFormData>;
    fields: FieldArrayWithId<any, any>[];
    schema: LiFormSchema;
    fieldName: string;
    remove: (idx: number) => void;
    swap: (a: number, b: number) => void;
    context: any;
}) => {
    return fields.map((item, idx) => (
        <Box key={idx} my={1}>
            <Box display="flex" gap={1}>
                <div
                    style={{
                        flexGrow: 1,
                    }}
                >
                    {renderField({
                        usedFormSubmit,
                        fieldName: `${fieldName}.${idx}`,
                        fieldSchema: {
                            ...schema,
                            defaultValue: undefined,
                            ...item,
                            showLabel: false,
                        },
                        context,
                    })}
                </div>

                <div>
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
                </div>
            </Box>
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
    const {t} = useTranslation();
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
                label={label}
            />
        );
    }

    return (
        <>
            <InputLabel>{label}</InputLabel>
            <Box
                sx={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 1,
                    mt: 1,
                }}
            >
                {renderArrayFields({
                    usedFormSubmit,
                    fields,
                    schema: {
                        ...schema.items,
                    },
                    fieldName,
                    remove,
                    swap,
                    context,
                })}
                <div>
                    <Button
                        variant="contained"
                        color="primary"
                        onClick={() =>
                            append(schema.type === 'object' ? {} : '')
                        }
                        startIcon={<AddIcon />}
                    >
                        {t('form.addItem', 'Add')}
                    </Button>
                </div>
            </Box>
        </>
    );
};

export default ArrayWidget;
