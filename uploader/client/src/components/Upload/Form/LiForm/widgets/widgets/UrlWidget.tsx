import React from 'react';
import BaseInputWidget from './BaseInputWidget';
import {WidgetProps} from '../types.ts';

export default function UrlWidget(props: WidgetProps) {
    return <BaseInputWidget {...props} type="url" />;
}
