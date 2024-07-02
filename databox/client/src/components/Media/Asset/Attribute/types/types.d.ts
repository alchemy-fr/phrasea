import {AttributeFormat} from '../Format/AttributeFormatContext';
import React from 'react';

export type AttributeWidgetProps<T> = {
    id: string;
    name: string;
    value: any;
    onChange: (value: T | undefined) => void;
    readOnly?: boolean;
    required?: boolean;
    autoFocus?: boolean;
    disabled?: boolean;
    isRtl?: boolean;
    indeterminate?: boolean;
    inputRef?: React.Ref<HTMLInputElement>;
};

export type AttributeFormat = string;

export type AttributeFormatterProps = {
    value: any;
    highlight?: any;
    format?: AttributeFormat;
    locale?: string | undefined;
};

export type AvailableFormat = {
    name: AttributeFormat;
    title: string;
};

export type AttributeTypeFormatter = {
    formatValue(props: AttributeFormatterProps): React.ReactNode;
    formatValueAsString(props: AttributeFormatterProps): string | undefined;
    getAvailableFormats(): AvailableFormat[];
};
export type AttributeTypeWidget<T> = {
    renderWidget(props: AttributeWidgetProps<T>): React.ReactNode;
    denormalize(value: any): any;
};

export type AttributeTypeInstance<T> = AttributeTypeFormatter &
    AttributeTypeWidget<T>;
export type AttributeTypeClass = {new (): AttributeTypeInstance<T>};
