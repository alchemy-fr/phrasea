import {LiFormSchema, UploadFormData} from '../../types.ts';
import React from 'react';
import BaseForm from './BaseForm.tsx';
import {useFormSubmit} from '@alchemy/api';
import compileSchema from './Form/LiForm/compileSchema.ts';

type Props = {
    schema: LiFormSchema;
    onSubmit: (data: UploadFormData) => Promise<void>;
    onCancel?: () => void;
};

export default function AssetLiForm({schema, onSubmit, onCancel}: Props) {
    const initialValues: UploadFormData = {};

    const properties = schema.properties;
    if (properties) {
        Object.keys(properties).forEach(k => {
            if (properties[k].defaultValue) {
                initialValues[k] = properties[k].defaultValue;
                delete properties[k].defaultValue;
            }
        });
    }

    const usedFormSubmit = useFormSubmit<UploadFormData>({
        defaultValues: initialValues,
        onSubmit: async data => {
            await onSubmit(data);

            return data;
        },
    });

    const {handleSubmit, submitting} = usedFormSubmit;

    const compiledSchema = compileSchema(schema);

    return (
        <BaseForm
            schema={compiledSchema}
            handleSubmit={handleSubmit}
            onCancel={onCancel}
            submitting={submitting}
            usedFormSubmit={usedFormSubmit}
        />
    );
}
