import React from 'react';
import {renderFields} from '../../renderFields.tsx';
import {WidgetProps} from '../types.ts';
import {InputLabel} from '@mui/material';

export default function ObjectWidget({schema, label, ...rest}: WidgetProps) {
    return (
        <>
            {label && <InputLabel>{label}</InputLabel>}
            {renderFields({
                schema,
                ...rest,
            })}
        </>
    );
}
