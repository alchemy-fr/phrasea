import React, {Component} from 'react';
import {Button, Form} from "react-bootstrap";
import FormPreview from "../FormPreview";
import Container from "../Container";
import {getFormSchema, getTargets} from "../../requests";
import FullPageLoader from "../FullPageLoader";
import {authenticatedRequest} from "../../lib/api";

export default class FormEditor extends Component {
    state = {
        targets: undefined,
        schema: undefined,
        selected: undefined,
        value: undefined,
        error: undefined,
        saved: false,
    };

    componentDidMount() {
        this.init();
    }

    async init() {
        this.setState({
            targets: await getTargets(),
        });
    }

    select(selected, e) {
        e.preventDefault();
        this.setState({
            selected,
            schema: undefined,
            error: undefined,
        });

        this.loadSchema(selected);
    }

    async loadSchema(id) {
        const schema = await getFormSchema(id);

        this.setState({
            schema: schema,
            value: schema ? JSON.stringify(schema.data, null, 4) : '{}',
        });
    }

    handleChange = event => {
        this.setState({
            value: event.target.value,
            saved: false,
            error: undefined,
        });
    };

    handleSubmit = async (event) => {
        event.preventDefault();

        const {schema, value, selected} = this.state;
        const data = {data: JSON.parse(value)};

        const requestConfig = {
            data,
        }

        if (schema) {
            requestConfig.method = 'PUT';
            requestConfig.url = `/form-schemas/${schema.id}`;
        } else {
            requestConfig.method = 'POST';
            requestConfig.url = `/form-schemas`;
            data.target = `/targets/${selected}`;
        }

        await authenticatedRequest(requestConfig);

        this.setState({
            saved: true,
        }, () => {
            setTimeout(() => {
                this.setState({saved: false});
            }, 3000);
        });
    };

    render() {
        const {saved, schema, error, targets, value, selected} = this.state;

        const loading = undefined === schema;

        if (!targets) {
            return <FullPageLoader/>
        }

        return <Container title="Form editor">
            <div className={'row'}>
                <div className={'col-md-3 col-sm-12'}>
                    <ul className="nav flex-column nav-pills">
                        {targets.map(t => <li
                            className="nav-item"
                            onClick={(e) => this.select(t.id, e)}
                            key={t.id}
                        >
                            <a className={`nav-link ${selected === t.id ? 'active' : ''}`} href="#">{t.name}</a>
                        </li>)}
                    </ul>
                </div>
                <div className={'col-md-9 col-sm-12'}>
                    {selected && <div className="row">
                        <div className="col">
                            <Form onSubmit={this.handleSubmit}>
                                <Form.Group controlId="schema">
                                    <Form.Label>JSON Schema</Form.Label>
                                    <Form.Control
                                        as="textarea"
                                        rows="20"
                                        value={loading ? 'Loading...' : value}
                                        disabled={loading}
                                        onChange={this.handleChange}
                                    />
                                </Form.Group>
                                {error && <div className="alert alert-danger">
                                    {error}
                                </div>}
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
                                    schema={value}
                                />}
                        </div>
                    </div>}
                </div>
            </div>
        </Container>
    }
}
