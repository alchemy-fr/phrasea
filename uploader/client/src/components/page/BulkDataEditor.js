import React, {Component} from 'react';
import {Form, Button} from "react-bootstrap";
import config from "../../config";
import request from "superagent";
import auth from "../../auth";
import Container from "../Container";

export default class BulkDataEditor extends Component {
    state = {
        bulkData: [],
        saved: false,
        error: null,
    };

    async componentWillMount() {
        let bulkData = await config.getBulkData();

        this.setState({
            bulkData: JSON.stringify(bulkData, true, 2)
        });
    }

    isObject = (value) => {
        return value && typeof value === 'object' && value.constructor === Object;
    };

    handleChange = event => {
        let error = null;
        try {
            const object = JSON.parse(event.target.value);
            if (!this.isObject(object)) {
                error = 'JSON should be a valid object (i.e. {"foo":"bar"})';
            }
        } catch (e) {
            error = e.toString();
        }

        this.setState({
            bulkData: event.target.value,
            saved: false,
            error,
        });
    };

    handleSubmit = event => {
        event.preventDefault();

        const accessToken = auth.getAccessToken();

        request
            .post(config.getUploadBaseURL() + '/bulk-data/edit')
            .accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .send({data: JSON.parse(this.state.bulkData)})
            .end((err, res) => {
                auth.isResponseValid(err, res);
            })
        ;

        this.setState({
            saved: true,
        }, () => {
            setTimeout(() => {
                this.setState({saved: false});
            }, 3000);
        });
    };

    render() {
        const {saved, bulkData, error} = this.state;

        const loading = null === bulkData;

        return (
            <Container title="Bulk data editor">
                <Form onSubmit={this.handleSubmit}>
                    <Form.Group controlId="json">
                        <Form.Label>JSON Data</Form.Label>
                        <Form.Control
                            as="textarea"
                            rows="5"
                            value={loading ? 'Loading...' : bulkData}
                            disabled={loading}
                            onChange={this.handleChange}
                        />
                    </Form.Group>
                    {error ? <div className="form-error">
                        {error}
                    </div>: ''}
                    <Button
                        disabled={null !== error}
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
            </Container>
        );
    }
}
