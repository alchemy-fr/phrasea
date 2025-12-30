import React from 'react';
import {LiFormField, UploadFormData} from '../../../../types.ts';
import widgets from './widgets';
import {mergeDeep} from '@alchemy/core';
import {UseFormSubmitReturn} from '@alchemy/api';

type WidgetType = keyof typeof widgets;

const guessWidget = (fieldSchema: LiFormField): string => {
    if (fieldSchema.widget) {
        return fieldSchema.widget;
    } else if (Object.prototype.hasOwnProperty.call(fieldSchema, 'enum')) {
        return 'choice';
    } else if (Object.prototype.hasOwnProperty.call(fieldSchema, 'oneOf')) {
        return 'oneOf';
    } else if (
        fieldSchema.format &&
        widgets[fieldSchema.format as WidgetType]
    ) {
        return fieldSchema.format!;
    }
    return fieldSchema.type || 'object';
};

type Props = {
    usedFormSubmit: UseFormSubmitReturn<UploadFormData>;
    fieldSchema: LiFormField;
    fieldName?: string;
    prefix?: string;
    context?: object;
    required?: boolean;
};

export const renderField = ({
    usedFormSubmit,
    fieldSchema,
    fieldName,
    prefix,
    context = {},
    required = false,
}: Props) => {
    if (Object.prototype.hasOwnProperty.call(fieldSchema, 'allOf')) {
        fieldSchema = mergeDeep(
            fieldSchema,
            ...fieldSchema.allOf!
        ) as LiFormField;
        delete fieldSchema.allOf;
    }

    const widget = guessWidget(fieldSchema);

    const newFieldName = prefix ? prefix + fieldName : fieldName;

    return React.createElement(widgets[widget as WidgetType], {
        usedFormSubmit,
        key: fieldName,
        fieldName: widget === 'oneOf' ? fieldName! : newFieldName!,
        label:
            fieldSchema.showLabel === false
                ? ''
                : fieldSchema.title || fieldName,
        required: required,
        schema: fieldSchema,
        context,
    });
};
