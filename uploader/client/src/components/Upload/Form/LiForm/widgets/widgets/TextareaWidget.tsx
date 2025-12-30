import React from 'react';
import BaseInputWidget from './BaseInputWidget';
import {WidgetProps} from '../types.ts';

export default function TextareaWidget(props: WidgetProps) {
    return <BaseInputWidget {...props} type="text" multiline={true} />;
}
