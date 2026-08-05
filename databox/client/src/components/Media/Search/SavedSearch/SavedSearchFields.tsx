import {TextField, Typography} from '@mui/material';
import React from 'react';
import {SavedSearch, SavedSearchPrivacy} from '../../../../types.ts';
import {useTranslation} from 'react-i18next';
import {UseFormSubmitReturn} from '@alchemy/api';
import {FormFieldErrors, FormRow, RadioWidget} from '@alchemy/react-form';
import {RemoteErrors} from '@alchemy/react-form';
import LockIcon from '@mui/icons-material/Lock';
import LinkIcon from '@mui/icons-material/Link';
import PublicIcon from '@mui/icons-material/Public';

type Props = {
    usedFormSubmit: UseFormSubmitReturn<SavedSearch>;
};

export default function SavedSearchFields({usedFormSubmit}: Props) {
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
                    label={t('form.saved_search.name.label', 'Name')}
                    disabled={submitting}
                    {...register('name', {
                        required: true,
                    })}
                />
                <FormFieldErrors field={'name'} errors={errors} />
            </FormRow>
            <FormRow>
                <RadioWidget
                    control={control}
                    name={'privacy'}
                    label={t('form.saved_search.privacy.label', 'Privacy')}
                    options={[
                        {
                            value: SavedSearchPrivacy.Secret,
                            icon: LockIcon,
                            label: (
                                <>
                                    <Typography>
                                        {t(
                                            'form.saved_search.privacy.secret.label',
                                            'Secret'
                                        )}
                                    </Typography>
                                    <Typography
                                        variant={'body2'}
                                        color={'text.secondary'}
                                    >
                                        {t(
                                            'form.saved_search.privacy.secret.helper',
                                            'Only you and granted users'
                                        )}
                                    </Typography>
                                </>
                            ),
                        },
                        {
                            value: SavedSearchPrivacy.Private,
                            icon: LinkIcon,
                            label: (
                                <>
                                    <Typography>
                                        {t(
                                            'form.saved_search.privacy.private.label',
                                            'Private'
                                        )}
                                    </Typography>
                                    <Typography
                                        variant={'body2'}
                                        color={'text.secondary'}
                                    >
                                        {t(
                                            'form.saved_search.privacy.private.helper',
                                            'Accessible by link, not listed'
                                        )}
                                    </Typography>
                                </>
                            ),
                        },
                        {
                            value: SavedSearchPrivacy.Public,
                            icon: PublicIcon,
                            label: (
                                <>
                                    <Typography>
                                        {t(
                                            'form.saved_search.privacy.public.label',
                                            'Public'
                                        )}
                                    </Typography>
                                    <Typography
                                        variant={'body2'}
                                        color={'text.secondary'}
                                    >
                                        {t(
                                            'form.saved_search.privacy.public.helper',
                                            'Listed to every user'
                                        )}
                                    </Typography>
                                </>
                            ),
                        },
                    ]}
                />
            </FormRow>
            <RemoteErrors errors={remoteErrors} />
        </>
    );
}
