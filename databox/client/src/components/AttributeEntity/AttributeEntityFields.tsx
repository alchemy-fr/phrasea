import {InputLabel, TextField} from '@mui/material';
import Flag from '../Ui/Flag.tsx';
import React from 'react';
import {AttributeEntity, EntityList, Workspace} from '../../types.ts';
import {useTranslation} from 'react-i18next';
import {UseFormSubmitReturn} from '@alchemy/api';
import {
    CollectionWidget,
    ColorWidget,
    FormFieldErrors,
    FormRow,
    KeyTranslationsWidget,
} from '@alchemy/react-form';
import KeyIcon from '@mui/icons-material/Key';
import InfoRow from '../Dialog/Info/InfoRow.tsx';
import {Controller} from 'react-hook-form';
import EmojiPicker from '../Discussion/EmojiPicker.tsx';

type Props = {
    workspace?: Workspace;
    usedFormSubmit: UseFormSubmitReturn<AttributeEntity, AttributeEntity>;
    data?: AttributeEntity;
    list: EntityList;
};

export default function AttributeEntityFields({
    workspace,
    data,
    usedFormSubmit,
    list: {withColors, withEmojis, withTranslations, withSynonyms},
}: Props) {
    const {t} = useTranslation();

    const {
        control,
        register,
        submitting,
        formState: {errors},
    } = usedFormSubmit;

    return (
        <>
            {data?.id ? (
                <FormRow>
                    <InfoRow
                        label={t('common.id', `ID`)}
                        value={data.id}
                        copyValue={data.id}
                        icon={<KeyIcon />}
                    />
                </FormRow>
            ) : null}
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
            {withEmojis && (
                <FormRow>
                    <InputLabel>
                        {t('form.attribute_entity.emoji.label', 'Emoji')}
                    </InputLabel>
                    <Controller
                        name={'emoji'}
                        control={control}
                        render={({field: {onChange, value}}) => {
                            return (
                                <EmojiPicker
                                    disabled={submitting}
                                    value={value}
                                    onSelect={emoji => {
                                        onChange(emoji);
                                    }}
                                />
                            );
                        }}
                    />
                    <FormFieldErrors field={'emoji'} errors={errors} />
                </FormRow>
            )}

            {withColors && (
                <FormRow>
                    <ColorWidget
                        label={t('form.attribute_entity.color.label', 'Color')}
                        control={control}
                        name={'color'}
                        disabled={submitting}
                    />
                    <FormFieldErrors field={'color'} errors={errors} />
                </FormRow>
            )}

            {withTranslations &&
            (workspace?.enabledLocales ?? []).length > 0 ? (
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
            {withSynonyms && (
                <FormRow>
                    <InputLabel>
                        {t('form.attribute_entity.synonyms.label', 'Synonyms')}
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
            )}
        </>
    );
}
