import React from 'react';
import {WidgetProps} from '../types';
import BaseInputWidget from './BaseInputWidget';

const PasswordWidget: React.FC<WidgetProps> = props => {
    return <BaseInputWidget type="password" {...props} />;
};

export default PasswordWidget;
