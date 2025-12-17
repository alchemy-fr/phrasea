import React, {FormEvent, useRef} from 'react';
import {Button, Modal} from 'react-bootstrap';
import {Trans} from 'react-i18next';
import {apiClient} from '../../../init.ts';

type Props = {
    url: string;
    onClose?: () => void;
};

export default function DownloadViaEmailModal({url, onClose}: Props) {
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
            <Modal show={true} onHide={onClose}>
                <form onSubmit={onSubmit}>
                    <Modal.Header closeButton>
                        <Modal.Title>
                            <Trans i18nKey={'download_via_email.cta'}>
                                Download via email
                            </Trans>
                        </Modal.Title>
                    </Modal.Header>

                    <Modal.Body>
                        {sent ? (
                            <p>
                                <Trans i18nKey={'download_via_email.sent'}>
                                    You will receive your download link by
                                    email.
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
                    </Modal.Body>

                    <Modal.Footer>
                        {sent ? (
                            <Button variant="secondary" onClick={onClose}>
                                <Trans i18nKey={'modal.close'}>Close</Trans>
                            </Button>
                        ) : (
                            <>
                                <Button
                                    onClick={onClose}
                                    disabled={submitting}
                                    variant="secondary"
                                >
                                    <Trans i18nKey={'modal.discard'}>
                                        Discard
                                    </Trans>
                                </Button>
                                <Button
                                    variant="primary"
                                    disabled={submitting}
                                    type={'submit'}
                                >
                                    <Trans i18nKey={'form.continue'}>
                                        Continue
                                    </Trans>
                                </Button>
                            </>
                        )}
                    </Modal.Footer>
                </form>
            </Modal>
        </>
    );
}
