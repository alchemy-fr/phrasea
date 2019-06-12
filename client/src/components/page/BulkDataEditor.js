import React, {Component} from 'react';
import {Form, Button} from "react-bootstrap";
import FormPreview from "../FormPreview";
import config from "../../config";
import request from "superagent";
import auth from "../../auth";

export default class BulkDataEditor extends Component {
    state = {
        bulkData: [],
        saved: false,
    };

    async componentWillMount() {
        let bulkData = await config.getBulkData();

        this.setState({
            bulkData: JSON.stringify(bulkData, true, 2)
        });
    }

    handleChange = event => {
        this.setState({
            bulkData: event.target.value,
            saved: false,
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
        const {saved, bulkData} = this.state;

        const loading = null === bulkData;

        return (
            <div className="container">
                <h1>Bulk data editor</h1>

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
