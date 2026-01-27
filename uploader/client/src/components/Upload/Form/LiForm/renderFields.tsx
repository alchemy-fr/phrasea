import {UseFormSubmitReturn} from '@alchemy/api';
import {LiFormSchema, UploadFormData} from '../../../../types.ts';
import {renderField} from './renderField.tsx';
import React from 'react';
import {FormRow} from '@alchemy/react-form';

export const isRequired = (
    schema: LiFormSchema,
    fieldName: string
): boolean => {
    if (!schema.required) {
        return false;
    }
    return schema.required.indexOf(fieldName) !== -1;
};

type Props = {
    usedFormSubmit: UseFormSubmitReturn<UploadFormData>;
    schema: LiFormSchema;
    prefix?: string;
    context?: object;
};

type PropOrder = {
    prop: string;
    propertyOrder: number;
};

export function renderFields({schema, ...props}: Props) {
    let fields: PropOrder[] = [];
    for (const i in schema.properties) {
        fields.push({
            prop: i,
            propertyOrder: schema.properties[i].propertyOrder ?? 0,
        });
    }
    fields = fields.sort((a, b) => {
        if (a.propertyOrder > b.propertyOrder) {
            return 1;
        } else if (a.propertyOrder < b.propertyOrder) {
            return -1;
        } else {
            return 0;
        }
    });

    return fields.map(item => {
        const name = item.prop;
        const field = schema.properties![name]!;

        return (
            <React.Fragment key={name}>
                <FormRow>
                    {renderField({
                        ...props,
                        fieldSchema: field,
                        fieldName: name,
                        required: isRequired(schema, name),
                    })}
                </FormRow>
            </React.Fragment>
        );
    });
}
