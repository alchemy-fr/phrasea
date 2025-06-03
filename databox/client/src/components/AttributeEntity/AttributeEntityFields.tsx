import {InputLabel, TextField} from '@mui/material';
import Flag from '../Ui/Flag.tsx';
import React from 'react';
import {AttributeEntity, Workspace} from '../../types.ts';
import {useTranslation} from 'react-i18next';
import {UseFormSubmitReturn} from '@alchemy/api';
import {
    CollectionWidget,
    FormFieldErrors,
    FormRow,
    KeyTranslationsWidget,
} from '@alchemy/react-form';
import RemoteErrors from '../Form/RemoteErrors.tsx';

type Props = {
    workspace?: Workspace;
    usedFormSubmit: UseFormSubmitReturn<AttributeEntity, AttributeEntity>;
};

export default function AttributeEntityFields({
    workspace,
    usedFormSubmit,
}: Props) {
    const {t} = useTranslation();

    const {
        control,
        register,
        remoteErrors,
        submitting,
        formState: {errors},
    } = usedFormSubmit;

    return (
        <>
            <FormRow>
                <TextField
                    autoFocus
                    required={true}
                    label={t('form.attribute_entity.value.label', 'Value')}
                    disabled={submitting}
                    {...register('value', {
                        required: true,
                    })}
                />
                <FormFieldErrors field={'value'} errors={errors} />
            </FormRow>

            {(workspace?.enabledLocales ?? []).length > 0 ? (
                <FormRow>
                    <KeyTranslationsWidget
                        renderLocale={l => {
                            return (
                                <Flag
                                    sx={{
                                        mr: 1,
                                    }}
                                    locale={l}
                                />
                            );
                        }}
                        locales={workspace?.enabledLocales ?? []}
                        name={'translations'}
                        errors={errors}
                        register={register}
                    />
                    <FormFieldErrors field={'translations'} errors={errors} />
                </FormRow>
            ) : null}
            <FormRow>
                <InputLabel>
                    {t('form.attribute_entity.synonym.label', 'Synonyms')}
                </InputLabel>
                <KeyTranslationsWidget
                    renderLocale={l => {
                        return (
                            <Flag
                                sx={{
                                    mr: 1,
                                }}
                                locale={l}
                            />
                        );
                    }}
                    locales={workspace?.enabledLocales ?? []}
                    name={'synonyms'}
                    errors={errors}
                    register={register}
                    renderField={({locale}) => {
                        return (
                            <CollectionWidget
                                path={`synonyms.${locale}`}
                                emptyItem={''}
                                errors={errors}
                                control={control}
                                register={register}
                                label={t(
                                    'form.attribute_entity.synonym.label',
                                    'Synonym'
                                )}
                                renderForm={({index}) => (
                                    <>
                                        <TextField
                                            disabled={submitting}
                                            {...register(
                                                `synonyms.${locale}.${index}` as any,
                                                {
                                                    required: true,
                                                }
                                            )}
                                        />
                                        <FormFieldErrors
                                            field={
                                                `synonyms.${locale}.${index}` as keyof AttributeEntity
                                            }
                                            errors={errors}
                                        />
                                    </>
                                )}
                            />
                        );
                    }}
                />

                <FormFieldErrors field={'synonyms'} errors={errors} />
            </FormRow>
            <RemoteErrors errors={remoteErrors} />
        </>
    );
}
