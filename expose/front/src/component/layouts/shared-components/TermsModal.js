import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {Modal, Button} from 'react-bootstrap';

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
                <p>
                    {text ? <div
                            className="terms-text"
                            dangerouslySetInnerHTML={{
                                __html: text,
                            }}
                        />
                        : <>
                            Please read and accept the{' '}
                            <a href={url} target={'_blank'}>terms</a>
                        </>}
                </p>
            </Modal.Body>

            <Modal.Footer>
                {closable ? <Button
                    onClick={onClose}
                    variant="secondary">Discard</Button> : ''}
                <Button variant="primary"
                        onClick={onAccept}
                >Accept</Button>
            </Modal.Footer>
        </Modal>
    }

}

