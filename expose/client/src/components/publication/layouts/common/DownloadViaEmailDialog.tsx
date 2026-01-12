import React from 'react';
import {useTranslation} from 'react-i18next';
import {apiClient} from '../../../../init.ts';
import {AppDialog} from '@alchemy/phrasea-ui';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {Button, TextField, Typography} from '@mui/material';
import {useFormSubmit} from '@alchemy/api';
import {FormRow, LoadingButton} from '@alchemy/react-form';

type FormData = {
    email: string;
};

type Props = {
    url: string;
} & StackedModalProps;

export default function DownloadViaEmailDialog({url, open}: Props) {
    const {t} = useTranslation();
    const [sent, setSent] = React.useState(false);
    const {closeModal} = useModals();

    const {handleSubmit, submitting, register} = useFormSubmit<FormData>({
        defaultValues: {
            email: '',
        },
        onSubmit: async data => {
            return await apiClient.post(url, {
                email: data.email,
            });
        },
        onSuccess: () => {
            setSent(true);
        },
    });
    const formId = 'download-via-email-form';

    return (
        <>
            <AppDialog
                open={open}
                maxWidth={'sm'}
                onClose={closeModal}
                title={t('download_via_email.cta', 'Download via email')}
                actions={({onClose}) => (
                    <>
                        {sent ? (
                            <Button onClick={onClose} disabled={submitting}>
                                {t('common.close', 'Close')}
                            </Button>
                        ) : (
                            <>
                                <Button onClick={onClose} disabled={submitting}>
                                    {t('download_via_email.cancel', 'Cancel')}
                                </Button>
                                <LoadingButton
                                    form={formId}
                                    variant="contained"
                                    disabled={submitting}
                                    loading={submitting}
                                    type={'submit'}
                                >
                                    {t(
                                        'download_via_email.submit_request',
                                        'Request Download'
                                    )}
                                </LoadingButton>
                            </>
                        )}
                    </>
                )}
            >
                <form onSubmit={handleSubmit} id={formId}>
                    {sent ? (
                        <Typography>
                            {t(
                                'download_via_email.sent',
                                'You will receive your download link by email.'
                            )}
                        </Typography>
                    ) : (
                        <FormRow>
                            <TextField
                                disabled={submitting}
                                label={t(
                                    'download_via_email.email.label',
                                    'Email'
                                )}
                                type="email"
                                required
                                fullWidth
                                helperText={t(
                                    'download_via_email.email.helper',
                                    'Enter the email address where you would like to receive the download link.'
                                )}
                                {...register('email', {required: true})}
                            />
                        </FormRow>
                    )}
                </form>
            </AppDialog>
        </>
    );
}
