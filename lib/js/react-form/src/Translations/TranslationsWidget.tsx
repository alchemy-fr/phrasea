import {Alert, Hidden, Stack, TextField} from '@mui/material';
import {BaseCollectionProps} from '../Collection/CollectionWidget';
import IconFormLabel from '../IconFormLabel';
import {Trans, useTranslation} from 'react-i18next';
import SortableCollectionWidget from '../Collection/SortableCollectionWidget';
import LocaleSelectWidget from '../Locale/LocaleSelectWidget';
import {EmojiFlags} from '@mui/icons-material';
import {TextFieldProps} from '@mui/material/TextField/TextField';
import FormRow from "../FormRow.tsx";
import FormFieldErrors from "../FormFieldErrors.tsx";

export type Translation = {
    locale: string;
    value: string;
};

const emptyTypedItem: Translation = {
    value: '',
    locale: '',
};

type Props<TFieldValues extends {translations: Translation[]}> = {
    inputProps?: TextFieldProps;
} & BaseCollectionProps<TFieldValues>;

export default function TranslationsWidget<
    TFieldValues extends {translations: Translation[]},
>({name, control, register, errors, max, inputProps}: Props<TFieldValues>) {
    const {t} = useTranslation('admin');

    return (
        <>
            <TextField
                fullWidth={true}
                label={t(
                    'form.translations.fallback.label',
                    'Default value',
                )}
                {...register(`fallback` as any)}
                required={true}
                {...(inputProps ?? {})}
            />
            <FormFieldErrors field={'fallback' as any} errors={errors} />

            <SortableCollectionWidget
                errors={errors}
                emptyItem={emptyTypedItem}
                max={max}
                control={control}
                label={
                    <IconFormLabel startIcon={<EmojiFlags />}>
                        {t(
                            'form.translations.collection.title',
                            'Translations',
                        )}
                    </IconFormLabel>
                }
                path={name}
                register={register}
                addLabel={t(
                    'form.translations.collection.add',
                    'Add new translation',
                )}
                removeLabel={
                    <Trans
                        t={t}
                        i18nKey="form.translations.collection.remove"
                    >
                        Remove <Hidden smDown>this translation</Hidden>
                    </Trans>
                }
                renderForm={({index, path}) => {
                    return (
                        <>
                            <FormRow>
                                {Boolean(max) && index >= max! ? (
                                    <Alert
                                        sx={{
                                            mb: 2,
                                        }}
                                        severity={'warning'}
                                    >
                                        {t(
                                            'form.translations.disabled_translation',
                                            'This translation is disabled',
                                        )}
                                    </Alert>
                                ) : (
                                    ''
                                )}
                                <Stack direction={'row'}>
                                    <div
                                        style={{
                                            width: 300,
                                        }}
                                    >
                                        <LocaleSelectWidget
                                            control={control}
                                            name={
                                                `${path}.${index}.locale` as any
                                            }
                                            placeholder={t(
                                                'form.translations.locale.placeholder',
                                                'Select locale',
                                            )}
                                            required={true}
                                        />
                                        <FormFieldErrors
                                            field={
                                                `${path}.${index}.locale` as any
                                            }
                                            errors={errors}
                                        />
                                    </div>
                                    <div>
                                        <TextField
                                            label={t(
                                                'form.translations.value.label',
                                                'Translation',
                                            )}
                                            required={true}
                                            {...register(
                                                `${path}.${index}.value` as any,
                                            )}
                                            {...(inputProps ?? {})}
                                        />
                                        <FormFieldErrors
                                            field={
                                                `${path}.${index}.value` as any
                                            }
                                            errors={errors}
                                        />
                                    </div>
                                </Stack>
                                <FormFieldErrors
                                    field={`${path}.${index}.value` as any}
                                    errors={errors}
                                />
                            </FormRow>
                        </>
                    );
                }}
            />
        </>
    );
}
