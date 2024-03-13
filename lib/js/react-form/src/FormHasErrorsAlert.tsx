import {CSSProperties} from 'react';
import FormError from './FormError';
import {FieldErrors} from 'react-hook-form';
import {FieldValues} from 'react-hook-form';
import {useTranslation} from 'react-i18next';

type Props<TFieldValues extends FieldValues> = {
    errors: FieldErrors<TFieldValues>;
    style?: CSSProperties;
};

export default function FormHasErrorsAlert<TFieldValues extends FieldValues>({
    errors,
    style,
}: Props<TFieldValues>) {
    const {t} = useTranslation();

    return (
        <>
            {errors && (
                <FormError style={style}>
                    {Object.keys(errors).length > 0 &&
                        t('form.has_errors', 'Form contains errors')}
                </FormError>
            )}
        </>
    );
}
