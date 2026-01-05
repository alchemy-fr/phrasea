import React from 'react';
import BaseInputWidget from './BaseInputWidget.tsx';
import {WidgetProps} from '../types.ts';

export default function DateTimeWidget(props: WidgetProps) {
    return (
        <BaseInputWidget
            type="datetime-local"
            {...props}
            InputLabelProps={{shrink: true}}
        />
    );
}
