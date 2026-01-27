import {UseFormSubmitReturn} from '@alchemy/api';
import {FieldValues} from 'react-hook-form';
import {Box, TextField} from '@mui/material';
import React from 'react';
import {useTranslation} from 'react-i18next';
import {FormFieldErrors, FormRow, SwitchWidget} from '@alchemy/react-form';

type Data = FieldValues;

type Props<TFieldValues extends Data> = {
    enabledLabel: string;
    path: string;
    usedFormSubmit: UseFormSubmitReturn<TFieldValues>;
    disabled?: boolean;
};

export default function TermsForm<TFieldValues extends Data>({
    enabledLabel,
    usedFormSubmit,
    path,
    disabled,
}: Props<TFieldValues>) {
    const {t} = useTranslation();

    const {
        register,
        control,
        submitting,
        formState: {errors},
        watch,
        setValue,
    } = usedFormSubmit;

    const enabled = watch(`${path}.enabled` as any);

    React.useEffect(() => {
        if (enabled === false) {
            setValue(`${path}.text` as any, null as any);
            setValue(`${path}.url` as any, null as any);
        }
    }, [setValue, enabled]);

    return (
        <Box
            sx={
                enabled
                    ? {
                          borderLeft: '5px solid',
                          borderColor: 'divider',
                          pl: 2,
                      }
                    : undefined
            }
        >
            <FormRow>
                <SwitchWidget
                    control={control}
                    label={enabledLabel}
                    name={`${path}.enabled` as any}
                    disabled={submitting || disabled}
                />
                <FormFieldErrors
                    field={`${path}.enabled` as any}
                    errors={errors}
                />
            </FormRow>
            {enabled && (
                <>
                    <FormRow>
                        <TextField
                            multiline={true}
                            minRows={4}
                            fullWidth={true}
                            label={t(
                                'form.publication.terms.text.label',
                                'Terms Content'
                            )}
                            disabled={submitting || disabled}
                            {...register(`${path}.text` as any)}
                        />
                        <FormFieldErrors
                            field={`${path}.text` as any}
                            errors={errors}
                        />
                    </FormRow>

                    <FormRow>
                        <TextField
                            label={t(
                                'form.publication.terms.url.label',
                                'Terms URL'
                            )}
                            helperText={t(
                                'form.publication.terms.url.helper',
                                'Alternatively, provide a URL to the terms and conditions.'
                            )}
                            type={'url'}
                            disabled={submitting || disabled}
                            {...register(`${path}.url` as any)}
                        />
                        <FormFieldErrors
                            field={`${path}.url` as any}
                            errors={errors}
                        />
                    </FormRow>
                </>
            )}
        </Box>
    );
}
