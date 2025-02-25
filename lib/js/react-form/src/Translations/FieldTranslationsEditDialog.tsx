import TranslationsWidget, {Translation} from './TranslationsWidget';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import FormHasErrorsAlert from '../FormHasErrorsAlert';
import Button from '@mui/material/Button';
import RemoteErrors from '../RemoteErrors';
import {StackedModalProps, useModals, useFormPrompt} from '@alchemy/navigation';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useFormSubmit} from '@alchemy/api';
import {TextFieldProps} from '@mui/material/TextField/TextField';
import {
    getFieldTranslationsList,
    getFieldTranslationsObject,
} from './localeHelper';
import {WithTranslations} from '../types';
import {locales} from '@alchemy/i18n/src/Locale/locales';
import LoadingButton from '../LoadingButton';

type Model = {
    fallback: string;
    translations: Translation[];
};

type Props<T extends WithTranslations> = {
    getData: () => T;
    onUpdate: (data: Partial<T>) => Promise<T>;
    title: string;
    field: keyof T & string;
    inputProps?: TextFieldProps;
    noToast?: boolean;
    maxTranslations?: number;
};

export type {Props as FieldTranslationsEditDialogProps};

export default function FieldTranslationsEditDialog<
    T extends WithTranslations,
>({
    getData,
    title,
    field,
    open,
    modalIndex,
    onUpdate,
    inputProps,
    noToast,
    maxTranslations,
}: Props<T> & StackedModalProps) {
    const {closeModal} = useModals();
    const {t} = useTranslation();

    maxTranslations ??= Object.keys(locales).length;

    const data = getData();

    const {
        control,
        register,
        handleSubmit,
        watch,
        formState: {errors},
        remoteErrors,
        submitting,
        forbidNavigation,
    } = useFormSubmit({
        defaultValues: {
            fallback: data[field] || '',
            translations: getFieldTranslationsList(data.translations, field),
        },
        onSubmit: async (d: Model) => {
            return await onUpdate({
                [field]: d.fallback,
                translations: {
                    [field]: getFieldTranslationsObject(d.translations),
                },
            } as unknown as Partial<T>);
        },
        onSuccess: () => {
            if (!noToast) {
                toast.success(
                    t('lib.form.translations.saved', 'Translations saved!')
                );
            }
            closeModal();
        },
        apiErrors: {
            normalizePath: p => p.replace('translations.title', 'translations'),
        },
    });

    const formId = field + 'Translations';

    useFormPrompt(t, forbidNavigation, modalIndex);

    watch('translations');

    return (
        <AppDialog
            onClose={closeModal}
            open={open}
            modalIndex={modalIndex}
            maxWidth={'md'}
            loading={submitting}
            title={title}
            actions={({onClose}) => (
                <>
                    <FormHasErrorsAlert
                        style={{
                            flexGrow: 1,
                        }}
                        errors={errors}
                    />
                    <Button onClick={onClose} disabled={submitting}>
                        {t('lib.form.cancel', 'Cancel')}
                    </Button>
                    <LoadingButton
                        type={'submit'}
                        form={formId}
                        loading={submitting}
                        disabled={submitting}
                    >
                        {t('lib.form.translations.submit', 'Save')}
                    </LoadingButton>
                </>
            )}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <TranslationsWidget
                    name={'translations'}
                    control={control}
                    errors={errors}
                    register={register}
                    max={maxTranslations}
                    inputProps={inputProps}
                />
            </form>
            <RemoteErrors errors={remoteErrors} />
        </AppDialog>
    );
}
