import React from 'react';
import {WidgetProps} from '../types';
import BaseInputWidget from './BaseInputWidget';

const MoneyWidget: React.FC<WidgetProps> = props => {
    return (
        <BaseInputWidget
            type="number"
            {...props}
            InputProps={{startAdornment: <span>€</span>}}
        />
    );
};

export default MoneyWidget;
