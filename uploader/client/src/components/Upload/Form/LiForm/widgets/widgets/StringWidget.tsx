import React from 'react';
import {WidgetProps} from '../types';
import BaseInputWidget from './BaseInputWidget';

const StringWidget: React.FC<WidgetProps> = props => {
    return <BaseInputWidget type="text" {...props} />;
};

export default StringWidget;
