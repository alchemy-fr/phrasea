import {Button, Modal} from 'react-bootstrap';
import {Trans} from 'react-i18next';

type Props = {
    closable?: boolean;
    text?: string;
    url?: string;
    title?: string;
    onClose?: () => void;
    onAccept: () => void;
};

export default function TermsModal({
    title,
    text,
    url,
    closable,
    onClose,
    onAccept,
}: Props) {
    return (
        <Modal show={true} onHide={onClose || (() => false)}>
            <Modal.Header closeButton={closable}>
                <Modal.Title>{title}</Modal.Title>
            </Modal.Header>

            <Modal.Body>
                <div>
                    {text && (
                        <div
                            className="terms-text"
                            dangerouslySetInnerHTML={{
                                __html: text,
                            }}
                        />
                    )}
                    {url && (
                        <div className={'terms-url'}>
                            {!text ? (
                                <>
                                    <Trans i18nKey={'terms.please_read_accept'}>
                                        Please read and accept the{' '}
                                        <a href={url} target={'_blank'}>
                                            terms
                                        </a>
                                    </Trans>
                                </>
                            ) : (
                                <a href={url} target={'_blank'}>
                                    <Trans i18nKey={'terms'}>terms</Trans>
                                </a>
                            )}
                        </div>
                    )}
                </div>
            </Modal.Body>

            <Modal.Footer>
                {closable ? (
                    <Button onClick={onClose} variant="secondary">
                        <Trans i18nKey={'modal.discard'}>Discard</Trans>
                    </Button>
                ) : (
                    ''
                )}
                <Button variant="primary" onClick={onAccept}>
                    <Trans i18nKey={'terms.accept'}>Accept</Trans>
                </Button>
            </Modal.Footer>
        </Modal>
    );
}
