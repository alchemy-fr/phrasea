import {FieldValues} from "react-hook-form";
import {UseFormSubmit} from '@alchemy/api';

export type FormProps<T extends FieldValues, D extends object = T> = {
    formId: string;
    usedFormSubmit: UseFormSubmit<T>;
    data?: D | undefined;
};
