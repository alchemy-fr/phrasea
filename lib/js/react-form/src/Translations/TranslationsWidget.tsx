import {Alert, Hidden, Stack, TextField} from '@mui/material';
import {BaseCollectionProps} from '../Collection/CollectionWidget';
import IconFormLabel from '../IconFormLabel';
import {Trans, useTranslation} from 'react-i18next';
import SortableCollectionWidget from '../Collection/SortableCollectionWidget';
import LocaleSelectWidget, {GetLocales} from '../Locale/LocaleSelectWidget';
import {EmojiFlags} from '@mui/icons-material';
import {TextFieldProps} from '@mui/material/TextField/TextField';
import FormRow from '../FormRow';
import FormFieldErrors from '../FormFieldErrors';

export type Translation = {
    locale: string;
    value: string;
};

const emptyTypedItem: Translation = {
    value: '',
    locale: '',
};

type Props<TFieldValues extends {translations: Translation[]}> = {
    getLocales: GetLocales;
} & {
    inputProps?: TextFieldProps;
} & BaseCollectionProps<TFieldValues>;

export default function TranslationsWidget<
    TFieldValues extends {translations: Translation[]},
>({getLocales, name, control, register, errors, max, inputProps}: Props<TFieldValues>) {
    const {t} = useTranslation();

    return (
        <>
            <TextField
                sx={{
                    mb: 2,
                }}
                fullWidth={true}
                label={t(
                    'lib.form.translations.fallback.label',
                    'Default value'
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
                            'lib.form.translations.collection.title',
                            'Translations'
                        )}
                    </IconFormLabel>
                }
                path={name}
                register={register}
                addLabel={t(
                    'lib.form.translations.collection.add',
                    'Add new translation'
                )}
                removeLabel={
                    <Trans t={t} i18nKey="form.translations.collection.remove">
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
                                            'lib.form.translations.disabled_translation',
                                            'This translation is disabled'
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
                                            getLocales={getLocales}
                                            control={control}
                                            name={
                                                `${path}.${index}.locale` as any
                                            }
                                            placeholder={t(
                                                'lib.form.translations.locale.placeholder',
                                                'Select locale'
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
                                                'lib.form.translations.value.label',
                                                'Translation'
                                            )}
                                            required={true}
                                            {...register(
                                                `${path}.${index}.value` as any
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
