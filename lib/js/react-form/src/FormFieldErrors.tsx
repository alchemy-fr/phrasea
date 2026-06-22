import FormError from './FormError';
import {FieldValues, FieldErrors} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {getObjectPropertyPath} from '@alchemy/api';

type Props<T extends FieldValues> = {
    field: keyof T;
    errors: FieldErrors<T>;
    hasTranslations?: boolean;
};

export default function FormFieldErrors<T extends FieldValues = FieldValues>({
    field,
    errors,
    hasTranslations,
}: Props<T>) {
    const error = getObjectPropertyPath(errors, field);
    if (!error) {
        return null;
    }

    if (hasTranslations && !error.message) {
        return Object.entries(error).map(([key, value]: any) => (
            <Error
                key={key}
                type={value.type}
                message={`[${key}] ${value.message}`}
            />
        ));
    }

    return <Error type={error.type} message={error.message} />;
}

function Error({type, message}: ErrorProps) {
    const {t} = useTranslation();

    return (
        <>
            {type === 'required' && (
                <FormError>
                    {t('lib.form.error.required', 'This field is required')}
                </FormError>
            )}
            {message ? <FormError>{message}</FormError> : null}
        </>
    );
}

type ErrorProps = {
    type: string;
    message?: string;
};
