import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {Modal, Button} from 'react-bootstrap';
import {Trans} from "react-i18next";

export default class TermsModal extends PureComponent {
    static propTypes = {
        closable: PropTypes.bool,
        text: PropTypes.string,
        url: PropTypes.string,
        onClose: PropTypes.func,
        onAccept: PropTypes.func.isRequired,
    };

    render() {
        const {
            title,
            text,
            url,
            closable,
            onClose,
            onAccept,
        } = this.props;

        return <Modal
            show={true}
            onHide={onClose || (() => false)}
        >
            <Modal.Header closeButton={closable}>
                <Modal.Title>{title}</Modal.Title>
            </Modal.Header>

            <Modal.Body>
                <div>
                    {text && <div
                        className="terms-text"
                        dangerouslySetInnerHTML={{
                            __html: text,
                        }}
                    />}
                    {url && <div className={'terms-url'}>
                        {!text ? <>
                            <Trans i18nKey={'terms.please_read_accept'}>
                                Please read and accept the <a href={url} target={'_blank'}>terms</a>
                            </Trans>
                        </> : <a href={url} target={'_blank'}>
                            <Trans i18nKey={'terms'}>
                                terms
                            </Trans>
                        </a>}
                    </div>}
                </div>
            </Modal.Body>

            <Modal.Footer>
                {closable ? <Button
                    onClick={onClose}
                    variant="secondary">
                    <Trans i18nKey={'modal.discard'}>Discard</Trans>
                </Button> : ''}
                <Button variant="primary"
                        onClick={onAccept}
                >
                    <Trans i18nKey={'terms.accept'}>Accept</Trans>
                </Button>
            </Modal.Footer>
        </Modal>
    }

}

