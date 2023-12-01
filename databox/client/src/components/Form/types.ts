import {UseFormSetError} from 'react-hook-form';

export type FormProps<T extends object, D extends object = T> = {
    formId: string;
    data?: D | undefined;
    onSubmit: (setError: UseFormSetError<T>) => (data: T) => Promise<void>;
    submitting: boolean;
    submitted: boolean;
};
