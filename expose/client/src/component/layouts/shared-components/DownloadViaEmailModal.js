import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {Modal, Button} from 'react-bootstrap';
import apiClient from "../../../lib/apiClient";
import {Trans} from "react-i18next";

export default class DownloadViaEmailModal extends PureComponent {
    static propTypes = {
        url: PropTypes.string.isRequired,
        onClose: PropTypes.func,
    };

    state = {
        email: '',
        submitting: false,
        sent: false,
    };

    constructor(props) {
        super(props);

        this.emailRef = React.createRef();
    }

    onSubmit = (e) => {
        e.preventDefault();
        this.setState({submitting: true}, async () => {
            const {email} = this.state;

            try {
                await apiClient.post(this.props.url, {
                    email,
                });
            } catch (e) {
                console.error(e);
            }

            this.setState({submitting: false, sent: true});
        });
    };

    render() {
        const {
            onClose,
        } = this.props;
        const {sent, submitting} = this.state;

        return <Modal
            show={true}
            onHide={onClose}
        >
            <form onSubmit={this.onSubmit}>
                <Modal.Header closeButton>
                    <Modal.Title><Trans i18nKey={'download_via_email'}>Download via email</Trans></Modal.Title>
                </Modal.Header>

                <Modal.Body>
                    {sent ? <p>
                        <Trans i18nKey={'download_via_email.sent'}>You will receive your download link by email.</Trans>

                    </p> : <div className="form-group">
                        <label htmlFor="email">
                            <Trans i18nKey={'email.label'}>Email</Trans>
                        </label>
                        <input
                            disabled={submitting}
                            id={'email'}
                            className={'form-control'}
                            ref={this.emailRef}
                            type="email"
                            required
                            onChange={e => this.setState({email: e.target.value})}
                            value={this.state.email}
                        />
                    </div>}
                </Modal.Body>

                <Modal.Footer>
                    {sent ? <Button
                        variant="secondary"
                        onClick={onClose}
                    ><Trans i18nKey={'modal.close'}>Close</Trans></Button> : <>
                        <Button
                            onClick={onClose}
                            disabled={submitting}
                            variant="secondary">

                            <Trans i18nKey={'modal.discard'}>Discard</Trans>
                        </Button>
                        <Button variant="primary"
                                disabled={submitting}
                                type={'submit'}
                        >
                            <Trans i18nKey={'form.continue'}>Continue</Trans>
                        </Button>
                    </>}
                </Modal.Footer>
            </form>
        </Modal>
    }

}

