import FormError from "./FormError";
import React from "react";
import {FieldValues} from "react-hook-form/dist/types/fields";
import {FieldErrors} from "react-hook-form/dist/types/errors";
import {useTranslation} from "react-i18next";

type Props<T extends FieldValues> = {
    field: keyof FieldErrors<T>;
    errors: FieldErrors<T>;
}

export default function FormFieldErrors<T extends FieldValues>({
                                                                   field,
                                                                   errors,
                                                               }: Props<T>) {
    const {t} = useTranslation();

    return <>
        {errors[field]?.type === 'required' &&
            <FormError>{t('form.error.required', 'This field is required')}</FormError>}
        {errors[field] && <FormError>{errors[field].message}</FormError>}
    </>
}
