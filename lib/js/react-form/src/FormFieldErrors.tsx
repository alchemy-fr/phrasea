import FormError from './FormError';
import {FieldValues, FieldErrors} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {getObjectPropertyPath} from '@alchemy/api';

type Props<T extends FieldValues> = {
    field: keyof T;
    errors: FieldErrors<T>;
};

export default function FormFieldErrors<T extends FieldValues = FieldValues>({
    field,
    errors,
}: Props<T>) {
    const {t} = useTranslation();

    const error = getObjectPropertyPath(errors, field);

    return (
        <>
            {error?.type === 'required' && (
                <FormError>
                    {t('lib.form.error.required', 'This field is required')}
                </FormError>
            )}
            {error && (
                <FormError>{error!.message as string}</FormError>
            )}
        </>
    );
}
