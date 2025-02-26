import {Stack, TextField} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {TextFieldProps} from '@mui/material/TextField/TextField';
import FormRow from '../FormRow';
import FormFieldErrors from '../FormFieldErrors';
import React, {ReactNode} from 'react';
import {FieldErrors, UseFormRegister} from 'react-hook-form';

type KeyTranslations = {
    [locale: string]: string;
};

type Props<TFieldValues extends {translations: KeyTranslations}> = {
    register: UseFormRegister<TFieldValues>;
    inputProps?: TextFieldProps;
    locales: string[];
    name: string;
    errors: FieldErrors<TFieldValues>;
    renderLocale: (locale: string) => ReactNode;
};

export default function KeyTranslationsWidget<
    TFieldValues extends {translations: KeyTranslations},
>({
    name,
    register,
    errors,
    inputProps,
    locales,
    renderLocale,
}: Props<TFieldValues>) {
    const {t} = useTranslation();

    const path = name;

    return (
        <>
            {locales.map(l => {
                return (
                    <React.Fragment key={l}>
                        <FormRow>
                            <Stack direction={'row'}>
                                <div
                                    style={{
                                        width: 50,
                                    }}
                                >
                                    {renderLocale(l)}
                                </div>
                                <div>
                                    <TextField
                                        label={t(
                                            'lib.form.translations.translation.label',
                                            {
                                                defaultValue:
                                                    'Translation {{locale}}',
                                                locale: l.toUpperCase(),
                                            }
                                        )}
                                        {...register(`${path}.${l}` as any)}
                                        {...(inputProps ?? {})}
                                    />
                                    <FormFieldErrors
                                        field={`${path}.${l}` as any}
                                        errors={errors}
                                    />
                                </div>
                            </Stack>
                        </FormRow>
                    </React.Fragment>
                );
            })}
        </>
    );
}

export function getNonEmptyTranslations(
    translations: KeyTranslations
): KeyTranslations {
    const tr: KeyTranslations = {};

    Object.keys(translations).forEach(key => {
        if (translations[key]) {
            tr[key] = translations[key];
        }
    });

    return tr;
}
