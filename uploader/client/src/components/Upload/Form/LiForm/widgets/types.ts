import {UseFormSubmitReturn} from '@alchemy/api';
import {LiFormField, UploadFormData} from '../../../../../types.ts';
import {ReactNode} from 'react';

export type FormFieldContext = Record<string, any>;

export type WidgetProps = {
    usedFormSubmit: UseFormSubmitReturn<UploadFormData>;
    fieldName: string;
    label?: ReactNode;
    schema: LiFormField;
    context: FormFieldContext;
    normalizer?: (value: any) => any;
    required?: boolean;
};
