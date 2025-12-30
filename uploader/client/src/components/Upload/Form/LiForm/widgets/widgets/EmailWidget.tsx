import React from 'react';
import {WidgetProps} from '../types';
import BaseInputWidget from './BaseInputWidget';

const EmailWidget: React.FC<WidgetProps> = props => {
    return <BaseInputWidget type="email" {...props} />;
};

export default EmailWidget;
