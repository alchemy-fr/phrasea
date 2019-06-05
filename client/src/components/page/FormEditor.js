import React, {Component} from 'react';
import {Form, Button} from "react-bootstrap";
import FormPreview from "../FormPreview";
import config from "../../config";
import request from "superagent";
import auth from "../../auth";

export default class FormEditor extends Component {
    constructor(props) {
        super(props);
        this.state = {
            schema: null,
            saved: false,
        }
    }

    async componentWillMount() {
        let schema = await config.getFormSchema();

        this.setState({
            schema: JSON.stringify(schema, true, 2)
        });
    }

    handleChange = event => {
        this.setState({
            [event.target.id]: event.target.value,
            saved: false,
        });
    };

    handleSubmit = event => {
        event.preventDefault();

        const accessToken = auth.getAccessToken();

        request
            .post(config.getUploadBaseURL() + '/form-schema/edit')
            .accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .send({schema: JSON.parse(this.state.schema)})
            .end()
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
        const {saved, schema} = this.state;

        const loading = null === schema;

        return (
            <div className="container">
                <h1>Form editor</h1>

                <div className="row">
                    <div className="col">
                        <Form onSubmit={this.handleSubmit}>
                            <Form.Group controlId="schema">
                                <Form.Label>JSON Schema</Form.Label>
                                <Form.Control
                                    as="textarea"
                                    rows="30"
                                    value={loading ? 'Loading...' : this.state.schema}
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
                    <div className="col">
                        {loading ? 'Loading...' :
                            <FormPreview
                                schema={schema}
                            />}
                    </div>
                </div>
            </div>
        );
    }
}
