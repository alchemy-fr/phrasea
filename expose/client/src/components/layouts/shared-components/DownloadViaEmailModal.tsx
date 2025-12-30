import React, {FormEvent, useRef} from 'react';
import {Trans} from 'react-i18next';
import {apiClient} from '../../../init.ts';
import {AppDialog} from '@alchemy/phrasea-ui';
import {StackedModalProps} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import {Button} from '@mui/material';

type Props = {
    url: string;
    onClose: () => void;
} & StackedModalProps;

export default function DownloadViaEmailModal({url, onClose, open}: Props) {
    const {t} = useTranslation();
    const [email, setEmail] = React.useState('');
    const [submitting, setSubmitting] = React.useState(false);
    const [sent, setSent] = React.useState(false);
    const emailRef = useRef<HTMLInputElement | null>(null);

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        (async () => {
            try {
                await apiClient.post(url, {
                    email,
                });
                setSent(true);
            } finally {
                setSubmitting(false);
            }
        })();
    };

    return (
        <>
            <AppDialog
                open={open}
                onClose={onClose}
                title={t('download_via_email.cta', 'Download via email')}
                actions={({onClose}) => (
                    <>
                        {sent ? (
                            <Button onClick={onClose}>
                                {t('common.close', 'Close')}
                            </Button>
                        ) : (
                            <>
                                <Button onClick={onClose} disabled={submitting}>
                                    {t('common.discard', 'Discard')}
                                </Button>
                                <Button
                                    color="primary"
                                    disabled={submitting}
                                    type={'submit'}
                                >
                                    {t(
                                        'download_via_email.continue',
                                        'Continue'
                                    )}
                                </Button>
                            </>
                        )}
                    </>
                )}
            >
                <form onSubmit={onSubmit}>
                    {sent ? (
                        <p>
                            <Trans i18nKey={'download_via_email.sent'}>
                                You will receive your download link by email.
                            </Trans>
                        </p>
                    ) : (
                        <div className="form-group">
                            <label htmlFor="email">
                                <Trans i18nKey={'email.label'}>Email</Trans>
                            </label>
                            <input
                                disabled={submitting}
                                id={'email'}
                                className={'form-control'}
                                ref={emailRef}
                                type="email"
                                required
                                onChange={e => setEmail(e.target.value)}
                                value={email}
                            />
                        </div>
                    )}
                </form>
            </AppDialog>
        </>
    );
}
