import FormError from './FormError';

import {FieldValues} from 'react-hook-form';
import {FieldErrors} from 'react-hook-form';
import {useTranslation} from 'react-i18next';

type Props<T extends FieldValues> = {
    field: keyof FieldErrors<T>;
    errors: FieldErrors<T>  ;
};

export default function FormFieldErrors<T extends FieldValues>({
    field,
    errors,
}: Props<T>) {
    const {t} = useTranslation();

    return <>
        {errors[field]?.type === 'required' && (
            <FormError>
                {t('form.error.required', 'This field is required')}
            </FormError>
        )}
        {errors[field] && <FormError>{errors[field]!.message as string}</FormError>}
    </>
}
