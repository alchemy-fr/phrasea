import {
    Button,
    FormHelperText,
    FormLabel,
    Hidden,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import UploadFileIcon from '@mui/icons-material/UploadFile';
import React, {FC} from 'react';
import {Trans, useTranslation} from 'react-i18next';
import {Workspace} from '../../types';
import {FormFieldErrors, TranslatedField} from '@alchemy/react-form';
import {FormRow} from '@alchemy/react-form';
import {FormProps} from './types';
import FlagIcon from '@mui/icons-material/Flag';
import IconFormLabel from './IconFormLabel';
import {SortableCollectionWidget} from '@alchemy/react-form';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {CheckboxWidget} from '@alchemy/react-form';
import {useCreateSaveTranslations} from '../../hooks/useCreateSaveTranslations.ts';
import {putWorkspace} from '../../api/collection.ts';
import {getLocaleOptions} from '../../api/locale.ts';
import {LocaleSelectWidget} from '@alchemy/react-form';
import AssetStatusSelect from './AssetStatusSelect.tsx';

const emptyLocaleItem = '';

export const WorkspaceForm: FC<FormProps<Workspace>> = function ({
    formId,
    data,
    setData,
    usedFormSubmit: {
        register,
        control,
        handleSubmit,
        watch,
        submitting,
        getValues,
        setValue,
        forbidNavigation,
        formState: {errors},
    },
}) {
    const {t} = useTranslation();

    const createSaveTranslations = useCreateSaveTranslations({
        data,
        setValue,
        putFn: putWorkspace,
        setData,
    });

    useDirtyFormPrompt(forbidNavigation);

    const enabledLocales = watch('enabledLocales');
    const termsPdf = watch('termsPdf');
    const logoUpload = watch('logoUpload');

    return (
        <>
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <TranslatedField<Workspace>
                        field={'name'}
                        getData={getValues}
                        locales={enabledLocales}
                        getLocales={getLocaleOptions}
                        title={t(
                            'form.workspace.title.translate.title',
                            'Translate Title'
                        )}
                        onUpdate={createSaveTranslations('name')}
                    >
                        <TextField
                            autoFocus
                            label={t('form.workspace.title.label', 'Title')}
                            disabled={submitting}
                            {...register('name', {
                                required: true,
                            })}
                        />
                    </TranslatedField>
                    <FormFieldErrors field={'name'} errors={errors} />
                </FormRow>
                <FormRow>
                    <CheckboxWidget
                        label={t('form.workspace.public.label', 'Public')}
                        control={control}
                        name={'public'}
                        disabled={submitting}
                    />
                    <FormFieldErrors field={'public'} errors={errors} />
                </FormRow>
                <FormRow>
                    <SortableCollectionWidget
                        errors={errors}
                        emptyItem={emptyLocaleItem}
                        control={control}
                        label={
                            <IconFormLabel startIcon={<FlagIcon />}>
                                {t(
                                    'form.workspace.locales.title',
                                    'Workspace locales'
                                )}
                            </IconFormLabel>
                        }
                        path={'enabledLocales'}
                        register={register}
                        addLabel={t('form.workspace.locales.add', 'Add locale')}
                        removeLabel={
                            <Trans
                                t={t}
                                i18nKey="form.workspace.locales.remove"
                            >
                                Remove <Hidden smDown>this locale</Hidden>
                            </Trans>
                        }
                        renderForm={({index, path}) => {
                            return (
                                <FormRow
                                    sx={{
                                        maxWidth: 300,
                                    }}
                                >
                                    <LocaleSelectWidget
                                        getLocales={getLocaleOptions}
                                        control={control}
                                        name={`${path}.${index}` as any}
                                        required={true}
                                        label={t(
                                            'form.workspace.locales.label',
                                            'Locale'
                                        )}
                                    />
                                </FormRow>
                            );
                        }}
                    />
                </FormRow>
                <FormRow>
                    <SortableCollectionWidget
                        errors={errors}
                        emptyItem={emptyLocaleItem}
                        control={control}
                        label={
                            <IconFormLabel startIcon={<FlagIcon />}>
                                {t(
                                    'form.workspace.fallback_locales.title',
                                    'Fallbacks locales'
                                )}
                            </IconFormLabel>
                        }
                        path={'localeFallbacks'}
                        register={register}
                        addLabel={t(
                            'form.workspace.fallback_locales.add',
                            'Add fallback locale'
                        )}
                        removeLabel={
                            <Trans
                                t={t}
                                i18nKey="form.workspace.fallback_locales.remove"
                            >
                                Remove <Hidden smDown>this locale</Hidden>
                            </Trans>
                        }
                        renderForm={({index, path}) => {
                            return (
                                <FormRow
                                    sx={{
                                        maxWidth: 300,
                                    }}
                                >
                                    <LocaleSelectWidget
                                        getLocales={getLocaleOptions}
                                        control={control}
                                        name={`${path}.${index}` as any}
                                        required={true}
                                        label={t(
                                            'form.workspace.fallback_locales.label',
                                            'Locale'
                                        )}
                                    />
                                </FormRow>
                            );
                        }}
                    />
                </FormRow>
                <FormRow>
                    <TextField
                        type={'number'}
                        label={t(
                            'form.workspace.trashRetentionDelay.label',
                            'Trash Retention Delay (in days)'
                        )}
                        disabled={submitting}
                        {...register('trashRetentionDelay')}
                    />
                    <FormFieldErrors
                        field={'trashRetentionDelay'}
                        errors={errors}
                    />
                </FormRow>
                <FormRow>
                    <AssetStatusSelect
                        control={control}
                        name={'assetDefaultStatus'}
                        label={t(
                            'form.workspace.assetDefaultStatus.label',
                            'Asset Default Status'
                        )}
                        disabled={submitting}
                    />
                    <FormFieldErrors
                        field={'assetDefaultStatus'}
                        errors={errors}
                    />
                </FormRow>
                <FormRow>
                    <CheckboxWidget
                        label={t(
                            'form.workspace.fileAnalysisRequired.label',
                            'Requires File Analysis'
                        )}
                        control={control}
                        name={'fileAnalysisRequired'}
                        disabled={submitting}
                    />
                    <FormFieldErrors
                        field={'fileAnalysisRequired'}
                        errors={errors}
                    />
                </FormRow>
                <FormRow>
                    <FormLabel>
                        {t('form.workspace.logo.label', 'Logo')}
                    </FormLabel>
                    <FormHelperText>
                        {t(
                            'form.workspace.logo.helper',
                            'Custom workspace logo. When none is set, the default service logo is used.'
                        )}
                    </FormHelperText>
                    <Stack
                        direction={'row'}
                        spacing={2}
                        alignItems={'center'}
                        sx={{mt: 1}}
                    >
                        {logoUpload === undefined && data?.logo ? (
                            <img
                                src={data.logo}
                                alt={''}
                                style={{maxHeight: 40, maxWidth: 160}}
                            />
                        ) : null}
                        {logoUpload instanceof File ? (
                            <Typography variant={'body2'}>
                                {t(
                                    'form.workspace.logo.selected',
                                    'New logo selected: {{name}}',
                                    {
                                        name: logoUpload.name,
                                    }
                                )}
                            </Typography>
                        ) : null}
                        {logoUpload === '' ? (
                            <Typography variant={'body2'} color={'error'}>
                                {t(
                                    'form.workspace.logo.removed',
                                    'The logo will be removed'
                                )}
                            </Typography>
                        ) : null}
                        <Button
                            component={'label'}
                            variant={'outlined'}
                            disabled={submitting}
                            startIcon={<UploadFileIcon />}
                        >
                            {t('form.workspace.logo.upload', 'Upload Logo')}
                            <input
                                type={'file'}
                                accept={
                                    'image/png,image/jpeg,image/gif,image/webp,image/svg+xml'
                                }
                                hidden
                                onChange={e => {
                                    const file = e.target.files?.[0];
                                    if (file) {
                                        setValue('logoUpload', file, {
                                            shouldDirty: true,
                                        });
                                    }
                                    e.target.value = '';
                                }}
                            />
                        </Button>
                        {logoUpload !== undefined || data?.logo ? (
                            <Button
                                color={'error'}
                                disabled={submitting}
                                onClick={() =>
                                    setValue(
                                        'logoUpload',
                                        logoUpload !== undefined
                                            ? undefined
                                            : '',
                                        {
                                            shouldDirty: true,
                                        }
                                    )
                                }
                            >
                                {logoUpload !== undefined
                                    ? t(
                                          'form.workspace.logo.cancel',
                                          'Cancel change'
                                      )
                                    : t(
                                          'form.workspace.logo.remove',
                                          'Remove Logo'
                                      )}
                            </Button>
                        ) : null}
                    </Stack>
                </FormRow>
                <FormRow>
                    <TranslatedField<any>
                        field={'terms'}
                        getData={() =>
                            ({
                                id: data?.id,
                                terms: getValues('termsText'),
                                translations: {
                                    terms: data?.terms?.translations ?? {},
                                },
                            }) as any
                        }
                        locales={enabledLocales}
                        getLocales={getLocaleOptions}
                        title={t(
                            'form.workspace.terms.translate.title',
                            'Translate Terms & Conditions'
                        )}
                        inputProps={{
                            multiline: true,
                            minRows: 4,
                        }}
                        onUpdate={async d => {
                            const r = await putWorkspace(data!.id, {
                                termsTranslations:
                                    (d as any).translations?.terms ?? {},
                            } as unknown as Partial<Workspace>);
                            setData?.(r);

                            return d;
                        }}
                    >
                        <TextField
                            label={t(
                                'form.workspace.terms.label',
                                'Terms & Conditions'
                            )}
                            disabled={submitting}
                            multiline={true}
                            minRows={4}
                            maxRows={20}
                            {...register('termsText')}
                            helperText={t(
                                'form.workspace.terms.helper',
                                'Changing this text or its translations creates a new version: users who signed a previous version will be asked to sign again.'
                            )}
                        />
                    </TranslatedField>
                    <FormFieldErrors field={'termsText'} errors={errors} />
                </FormRow>
                <FormRow>
                    <FormLabel>
                        {t(
                            'form.workspace.termsPdf.label',
                            'Terms & Conditions PDF'
                        )}
                    </FormLabel>
                    <FormHelperText>
                        {t(
                            'form.workspace.termsPdf.helper',
                            'You can provide the Terms & Conditions directly as a PDF; it takes precedence over the text above.'
                        )}
                    </FormHelperText>
                    <Stack
                        direction={'row'}
                        spacing={2}
                        alignItems={'center'}
                        sx={{mt: 1}}
                    >
                        {termsPdf === undefined && data?.terms?.pdfUrl ? (
                            <Button
                                href={data.terms.pdfUrl}
                                target={'_blank'}
                                rel={'noreferrer'}
                            >
                                {t(
                                    'form.workspace.termsPdf.view',
                                    'View current PDF (v{{version}})',
                                    {
                                        version: data.terms.version,
                                    }
                                )}
                            </Button>
                        ) : null}
                        {termsPdf instanceof File ? (
                            <Typography variant={'body2'}>
                                {t(
                                    'form.workspace.termsPdf.selected',
                                    'New PDF selected: {{name}} (will create a new version)',
                                    {
                                        name: termsPdf.name,
                                    }
                                )}
                            </Typography>
                        ) : null}
                        {termsPdf === '' ? (
                            <Typography variant={'body2'} color={'error'}>
                                {t(
                                    'form.workspace.termsPdf.removed',
                                    'The PDF will be removed'
                                )}
                            </Typography>
                        ) : null}
                        <Button
                            component={'label'}
                            variant={'outlined'}
                            disabled={submitting}
                            startIcon={<UploadFileIcon />}
                        >
                            {t('form.workspace.termsPdf.upload', 'Upload PDF')}
                            <input
                                type={'file'}
                                accept={'application/pdf'}
                                hidden
                                onChange={e => {
                                    const file = e.target.files?.[0];
                                    if (file) {
                                        setValue('termsPdf', file, {
                                            shouldDirty: true,
                                        });
                                    }
                                    e.target.value = '';
                                }}
                            />
                        </Button>
                        {termsPdf !== undefined || data?.terms?.pdfUrl ? (
                            <Button
                                color={'error'}
                                disabled={
                                    submitting ||
                                    (termsPdf === '' && !data?.terms?.pdfUrl)
                                }
                                onClick={() =>
                                    setValue(
                                        'termsPdf',
                                        termsPdf !== undefined ? undefined : '',
                                        {
                                            shouldDirty: true,
                                        }
                                    )
                                }
                            >
                                {termsPdf !== undefined
                                    ? t(
                                          'form.workspace.termsPdf.cancel',
                                          'Cancel change'
                                      )
                                    : t(
                                          'form.workspace.termsPdf.remove',
                                          'Remove PDF'
                                      )}
                            </Button>
                        ) : null}
                    </Stack>
                </FormRow>
                <FormRow>
                    <CheckboxWidget
                        label={t(
                            'form.workspace.attachTermsToExports.label',
                            'Attach Terms & Conditions PDF to exports'
                        )}
                        control={control}
                        name={'attachTermsToExports'}
                        disabled={submitting}
                    />
                    <FormFieldErrors
                        field={'attachTermsToExports'}
                        errors={errors}
                    />
                </FormRow>
            </form>
        </>
    );
};
