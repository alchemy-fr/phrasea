import React from 'react';
import {WidgetProps} from '../types';
import BaseInputWidget from './BaseInputWidget';

const SearchWidget: React.FC<WidgetProps> = props => {
    return <BaseInputWidget type="search" {...props} />;
};

export default SearchWidget;
