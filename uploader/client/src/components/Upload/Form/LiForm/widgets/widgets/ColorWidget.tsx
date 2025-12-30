import React from 'react';
import {WidgetProps} from '../types';
import BaseInputWidget from './BaseInputWidget';

const ColorWidget: React.FC<WidgetProps> = props => {
    return <BaseInputWidget type="color" {...props} />;
};

export default ColorWidget;
