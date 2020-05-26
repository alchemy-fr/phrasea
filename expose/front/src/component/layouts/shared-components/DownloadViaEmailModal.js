import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {Modal, Button} from 'react-bootstrap';
import apiClient from "../../../lib/apiClient";

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

    onSubmit = () => {
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
            <Modal.Header closeButton>
                <Modal.Title>Download via email</Modal.Title>
            </Modal.Header>

            <Modal.Body>
                {sent ? <p>
                    You will receive your download link by email.
                </p> : <div className="form-group">
                    <label htmlFor="email">
                        Email
                    </label>
                    <input
                        disabled={submitting}
                        id={'email'}
                        className={'form-control'}
                        ref={this.emailRef}
                        type="email"
                        onChange={e => this.setState({email: e.target.value})}
                        value={this.state.email}
                    />
                </div>}
            </Modal.Body>

            <Modal.Footer>
                {sent ? <Button
                    variant="secondary"
                    onClick={onClose}
                >Close</Button> : <>
                    <Button
                        onClick={onClose}
                        disabled={submitting}
                        variant="secondary">Discard</Button>
                    <Button variant="primary"
                            disabled={submitting}
                            onClick={this.onSubmit}
                    >Continue</Button>
                </>}
            </Modal.Footer>
        </Modal>
    }

}

