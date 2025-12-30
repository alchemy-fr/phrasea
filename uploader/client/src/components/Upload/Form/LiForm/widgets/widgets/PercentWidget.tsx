import React from 'react';
import {WidgetProps} from '../types';
import BaseInputWidget from './BaseInputWidget';

const PercentWidget: React.FC<WidgetProps> = props => {
    return (
        <BaseInputWidget
            type="number"
            {...props}
            InputProps={{endAdornment: <span>%</span>}}
        />
    );
};

export default PercentWidget;
