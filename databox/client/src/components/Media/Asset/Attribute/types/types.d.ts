import React from "react";
import {AttributeFormat} from "../Format/AttributeFormatContext";

export type AttributeWidgetProps = {
    id: string;
    name: string;
    value: any;
    onChange: (value: any) => void;
    readOnly?: boolean;
    required?: boolean;
    autoFocus?: boolean;
    disabled?: boolean;
    isRtl?: boolean;
    indeterminate?: boolean;
};

export type AttributeFormat = string;

export type AttributeFormatterProps = {
    value: any;
    highlight?: any;
    format?: AttributeFormat;
    multiple?: boolean;
    locale?: string | undefined;
};

export type AvailableFormat = {
    name: AttributeFormat;
    title: string;
}

export type AttributeTypeFormatter = {
    supportsMultiple(): boolean;
    formatValue(props: AttributeFormatterProps): React.ReactNode;
    formatValueAsString(props: AttributeFormatterProps): string | undefined;

    getAvailableFormats(): AvailableFormat[];
}
export type AttributeTypeWidget = {
    renderWidget(props: AttributeWidgetProps): React.ReactNode;
}

export type AttributeTypeInstance = AttributeTypeFormatter & AttributeTypeWidget;
export type AttributeTypeClass = { new(): AttributeTypeInstance };
