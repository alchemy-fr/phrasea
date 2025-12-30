import React from 'react';
import {renderFields} from '../../renderFields.tsx';
import {WidgetProps} from '../types.ts';
import {InputLabel} from '@mui/material';
import {FormRow} from '@alchemy/react-form';

export default function ObjectWidget({schema, label, ...rest}: WidgetProps) {
    return (
        <FormRow>
            {label && <InputLabel>{label}</InputLabel>}
            {renderFields({
                schema,
                ...rest,
            })}
        </FormRow>
    );
}
