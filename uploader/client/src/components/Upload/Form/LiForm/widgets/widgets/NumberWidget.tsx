import React from 'react';
import {WidgetProps} from '../types';
import BaseInputWidget from './BaseInputWidget';

const NumberWidget: React.FC<WidgetProps> = props => {
    return <BaseInputWidget type="number" {...props} normalizer={parseFloat} />;
};

export default NumberWidget;
