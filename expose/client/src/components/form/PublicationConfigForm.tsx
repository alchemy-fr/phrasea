import {UseFormSubmitReturn} from '@alchemy/api';
import {FieldValues} from 'react-hook-form';
import {TextField} from '@mui/material';
import React, {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {
    DateWidget,
    FormFieldErrors,
    FormRow,
    RadioWidget,
    SwitchWidget,
} from '@alchemy/react-form';
import {LayoutEnum, PublicationConfig, SecurityMethod} from '../../types.ts';
import GridViewIcon from '@mui/icons-material/GridView';
import BurstModeIcon from '@mui/icons-material/BurstMode';
import {SvgIconComponent} from '@mui/icons-material';
import CloudDownloadIcon from '@mui/icons-material/CloudDownload';
import MapIcon from '@mui/icons-material/Map';
import VerifiedUserIcon from '@mui/icons-material/VerifiedUser';
import PasswordIcon from '@mui/icons-material/Password';
import PublicIcon from '@mui/icons-material/Public';
import TermsForm from './TermsForm.tsx';

type Data = {
    config: PublicationConfig;
} & FieldValues;

type Props<TFieldValues extends Data> = {
    path: string;
    usedFormSubmit: UseFormSubmitReturn<TFieldValues>;
};

export default function PublicationConfigForm<TFieldValues extends Data>({
    usedFormSubmit,
    path,
}: Props<TFieldValues>) {
    const {t} = useTranslation();

    const layoutTranslations: Record<LayoutEnum, string> = useMemo(
        () => ({
            [LayoutEnum.Gallery]: t(
                'form.publication.config.layout.options.gallery',
                'Gallery'
            ),
            [LayoutEnum.Grid]: t(
                'form.publication.config.layout.options.grid',
                'Grid'
            ),
            [LayoutEnum.Download]: t(
                'form.publication.config.layout.options.download',
                'Download'
            ),
            [LayoutEnum.Mapbox]: t(
                'form.publication.config.layout.options.mapbox',
                'Mapbox'
            ),
        }),
        [t]
    );

    const layoutIcons: Record<LayoutEnum, SvgIconComponent> = {
        [LayoutEnum.Gallery]: GridViewIcon,
        [LayoutEnum.Grid]: BurstModeIcon,
        [LayoutEnum.Download]: CloudDownloadIcon,
        [LayoutEnum.Mapbox]: MapIcon,
    };

    const securityMethodTranslations: Record<SecurityMethod, string> = useMemo(
        () => ({
            [SecurityMethod.Public]: t(
                'form.publication.config.securityMethod.options.public',
                'Public'
            ),
            [SecurityMethod.Authentication]: t(
                'form.publication.config.securityMethod.options.authentication',
                'Authentication'
            ),
            [SecurityMethod.Password]: t(
                'form.publication.config.securityMethod.options.password',
                'Password'
            ),
        }),
        [t]
    );

    const securityMethodIcons: Record<SecurityMethod, SvgIconComponent> = {
        [SecurityMethod.Public]: PublicIcon,
        [SecurityMethod.Authentication]: VerifiedUserIcon,
        [SecurityMethod.Password]: PasswordIcon,
    };

    const {
        register,
        control,
        submitting,
        formState: {errors},
        watch,
    } = usedFormSubmit;

    const displayPassword =
        watch(`${path}.securityMethod` as any) === SecurityMethod.Password;

    return (
        <>
            <FormRow>
                <SwitchWidget
                    control={control}
                    label={t(
                        'form.publication.config.enabled.label',
                        'Enabled'
                    )}
                    name={`${path}.enabled` as any}
                    disabled={submitting}
                />
                <FormFieldErrors
                    field={`${path}.enabled` as any}
                    errors={errors}
                />
            </FormRow>
            <FormRow>
                <DateWidget
                    control={control}
                    label={t(
                        'form.publication.config.beginsAt.label',
                        'Begins At'
                    )}
                    time={true}
                    name={`${path}.beginsAt` as any}
                    disabled={submitting}
                />
                <FormFieldErrors
                    field={`${path}.beginsAt` as any}
                    errors={errors}
                />
            </FormRow>
            <FormRow>
                <DateWidget
                    control={control}
                    label={t(
                        'form.publication.config.expiresAt.label',
                        'Expires At'
                    )}
                    time={true}
                    name={`${path}.expiresAt` as any}
                    disabled={submitting}
                />
                <FormFieldErrors
                    field={`${path}.expiresAt` as any}
                    errors={errors}
                />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    control={control}
                    label={t(
                        'form.publication.config.publiclyListed.label',
                        'Publicly Listed'
                    )}
                    name={`${path}.publiclyListed` as any}
                    disabled={submitting}
                />
                <FormFieldErrors
                    field={`${path}.publiclyListed` as any}
                    errors={errors}
                />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    control={control}
                    label={t(
                        'form.publication.config.downloadEnabled.label',
                        'Download Enabled'
                    )}
                    name={`${path}.downloadEnabled` as any}
                    disabled={submitting}
                />
                <FormFieldErrors
                    field={`${path}.downloadEnabled` as any}
                    errors={errors}
                />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    control={control}
                    label={t(
                        'form.publication.config.downloadViaEmail.label',
                        'Download via Email'
                    )}
                    name={`${path}.downloadViaEmail` as any}
                    disabled={submitting}
                />
                <FormFieldErrors
                    field={`${path}.downloadViaEmail` as any}
                    errors={errors}
                />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    control={control}
                    label={t(
                        'form.publication.config.includeDownloadTermsInZippy.label',
                        'Include Download Terms in Archive'
                    )}
                    name={`${path}.includeDownloadTermsInZippy` as any}
                    disabled={submitting}
                />
                <FormFieldErrors
                    field={`${path}.includeDownloadTermsInZippy` as any}
                    errors={errors}
                />
            </FormRow>
            <FormRow>
                <TextField
                    label={t('form.publication.config.css.label', 'CSS')}
                    disabled={submitting}
                    {...register(`${path}.css` as any)}
                />
                <FormFieldErrors field={`${path}.css` as any} errors={errors} />
            </FormRow>
            <FormRow>
                <RadioWidget
                    control={control}
                    label={t('form.publication.config.layout.label', 'Layout')}
                    name={`${path}.layout` as any}
                    disabled={submitting}
                    options={Object.values(LayoutEnum).map(layout => ({
                        label: layoutTranslations[layout],
                        value: layout,
                        icon: layoutIcons[layout],
                    }))}
                />
                <FormFieldErrors
                    field={`${path}.layout` as any}
                    errors={errors}
                />
            </FormRow>
            <FormRow>
                <RadioWidget
                    control={control}
                    label={t(
                        'form.publication.config.securityMethod.label',
                        'Security Method'
                    )}
                    name={`${path}.securityMethod` as any}
                    disabled={submitting}
                    options={Object.values(SecurityMethod).map(method => ({
                        label: securityMethodTranslations[method],
                        value: method,
                        icon: securityMethodIcons[method],
                    }))}
                />
                <FormFieldErrors
                    field={`${path}.securityMethod` as any}
                    errors={errors}
                />
            </FormRow>
            {displayPassword && (
                <FormRow>
                    <TextField
                        label={t(
                            'form.publication.config.securityOptions.password.label',
                            'Password'
                        )}
                        disabled={submitting}
                        {...register(`${path}.securityOptions.password` as any)}
                    />
                    <FormFieldErrors
                        field={`${path}.securityOptions.password` as any}
                        errors={errors}
                    />
                </FormRow>
            )}
            <TermsForm
                usedFormSubmit={usedFormSubmit}
                path={`${path}.terms` as any}
                enabledLabel={t(
                    'form.publication.config.terms.enabledLabel',
                    'Enable Terms and Conditions'
                )}
            />
            <TermsForm
                usedFormSubmit={usedFormSubmit}
                path={`${path}.downloadTerms` as any}
                enabledLabel={t(
                    'form.publication.config.downloadTerms.enabledLabel',
                    'Enable Download Terms and Conditions'
                )}
            />
        </>
    );
}
