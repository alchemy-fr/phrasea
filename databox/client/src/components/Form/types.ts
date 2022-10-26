import {UseFormSetError} from "react-hook-form/dist/types/form";

export type FormProps<T extends object> = {
    formId: string;
    data?: T | undefined;
    onSubmit: (setError: UseFormSetError<T>) => (data: T) => Promise<void>;
    submitting: boolean;
    submitted: boolean;
};
