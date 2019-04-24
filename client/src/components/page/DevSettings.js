import React, {Component} from 'react';
import config from '../../store/config';
import {Form, Button} from "react-bootstrap";

export default class DevSettings extends Component {
    constructor(props) {
        super(props);

        const {clientId, clientSecret} = config.getClientCredential();

        this.state = {
            uploadBaseUrl: config.getUploadBaseURL() || '',
            clientId: clientId || '',
            clientSecret: clientSecret || '',
            saved: false,
        }
    }

    handleChange = event => {
        this.setState({
            [event.target.id]: event.target.value,
            saved: false,
        });
    };

    handleSubmit = event => {
        event.preventDefault();

        config.setUploadBaseURL(this.state.uploadBaseUrl);
        config.setClientCredential({
            clientId: this.state.clientId,
            clientSecret: this.state.clientSecret,
        });

        this.setState({
            saved: true,
        }, () => {
            setTimeout(() => {
                this.setState({saved: false});
            }, 3000);
        });
    };

    render() {
        const {saved} = this.state;

        return (
            <div className="container">
                <h1>DEV Settings</h1>

                <Form onSubmit={this.handleSubmit}>
                    <Form.Group controlId="uploadBaseUrl">
                        <Form.Label>Upload Base URL</Form.Label>
                        <Form.Control
                            type="text"
                            value={this.state.uploadBaseUrl}
                            onChange={this.handleChange}
                        />
                    </Form.Group>
                    <Form.Group controlId="clientId">
                        <Form.Label>Client ID</Form.Label>
                        <Form.Control
                            value={this.state.clientId}
                            onChange={this.handleChange}
                            type="text"
                        />
                    </Form.Group>
                    <Form.Group controlId="clientSecret">
                        <Form.Label>Client secret</Form.Label>
                        <Form.Control
                            value={this.state.clientSecret}
                            onChange={this.handleChange}
                            type="text"
                        />
                    </Form.Group>
                    <Button
                        block
                        type="submit"
                    >
                        Save
                    </Button>
                    {saved ? (<span>
                        {' '}
                            <span className="badge badge-success">saved!</span>
                        </span>
                    ) : ''}
                </Form>
            </div>
        );
    }
}
