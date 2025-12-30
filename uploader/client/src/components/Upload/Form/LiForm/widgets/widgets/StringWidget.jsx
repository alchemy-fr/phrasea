import React from 'react';
import BaseInputWidget from './BaseInputWidget';

export default function StringWidget(props) {
    return <BaseInputWidget type="text" {...props} />;
}
