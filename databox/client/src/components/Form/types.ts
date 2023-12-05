import {FieldValues} from 'react-hook-form';
import {UseFormSubmitReturn} from '@alchemy/api';

export type FormProps<T extends FieldValues, D extends object = T> = {
    formId: string;
    usedFormSubmit: UseFormSubmitReturn<T, D>;
    data?: D | undefined;
};
