import {UseFormHandleSubmit} from 'react-hook-form/dist/types/form';

export type FormProps<T extends object, D extends object = T> = {
    formId: string;
    data?: D | undefined;
    onSubmit: UseFormHandleSubmit<T>;
    submitting: boolean;
    submitted: boolean;
};
