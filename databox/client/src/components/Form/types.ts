import {FieldValues} from 'react-hook-form';
import {UseFormSubmitReturn} from '@alchemy/api';
import {AQLConstant} from '../Media/Search/AQL/aqlTypes.ts';

export type FormProps<T extends FieldValues, D extends object = T> = {
    formId: string;
    usedFormSubmit: UseFormSubmitReturn<T, D>;
    data?: D | undefined;
    setData?: (data: D) => void;
};
export enum NullableBooleanValue {
    True = 'true',
    False = 'false',
    Unset = `=${AQLConstant.Null}`,
}
