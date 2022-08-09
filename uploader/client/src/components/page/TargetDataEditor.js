import React, {Component} from 'react';
import {Button, Form} from "react-bootstrap";
import config from "../../config";
import request from "superagent";
import Container from "../Container";
import {oauthClient} from "../../oauth";
import {getTargetParams, getTargets} from "../../requests";
import {FullPageLoader} from "@alchemy-fr/phraseanet-react-components";

export default class TargetDataEditor extends Component {
    state = {
        targets: undefined,
        params: undefined,
        value: undefined,
        selected: null,
        saved: false,
        error: null,
    };

    componentDidMount() {
        this.init();
    }

    async init() {
        this.setState({
            targets: await getTargets(),
        });
    }

    async loadParams(id) {
        const targetParams = await getTargetParams(id);
        const r = targetParams.length === 0 ? null : targetParams[0];

        this.setState({
            params: r,
            value: r ? JSON.stringify(r.data, null, 4) : '{}',
        });
    }

    isObject = (value) => {
        return value && typeof value === 'object' && value.constructor === Object;
    };

    handleChange = event => {
        let error = null;
        const v = event.target.value;
        try {
            const object = JSON.parse(v);
            if (!this.isObject(object)) {
                error = 'JSON should be a valid object (i.e. {"foo":"bar"})';
            }
        } catch (e) {
            error = e.toString();
        }

        this.setState({
            value: v,
            saved: false,
            error,
        });
    };

    handleSubmit = event => {
        event.preventDefault();

        const {params, value, selected} = this.state;
        const accessToken = oauthClient.getAccessToken();

        let r, data = {data: JSON.parse(value)};
        if (params) {
            r = request.put(`${config.getUploadBaseURL()}/target-params/${params.id}`);
        } else {
            r = request.post(`${config.getUploadBaseURL()}/target-params`);
            data = {
                ...data,
                target: `/targets/${selected}`
            };
        }

        r.accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .send(data)
            .end((err, res) => {
                oauthClient.isResponseValid(err, res);
            })

        this.setState({
            saved: true,
        }, () => {
            setTimeout(() => {
                this.setState({saved: false});
            }, 3000);
        });
    };

    select(selected, e) {
        e.preventDefault();
        this.setState({
            selected,
            params: undefined,
        });

        this.loadParams(selected);
    }

    render() {
        const {targets, value, selected, saved, params, error} = this.state;

        if (!targets) {
            return <FullPageLoader/>
        }

        const loading = undefined === params;

        return <Container title="Target parameters editor">
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
                    {selected && <Form onSubmit={this.handleSubmit}>
                        <Form.Group controlId="json">
                            <Form.Label>JSON Data</Form.Label>
                            <Form.Control
                                as="textarea"
                                rows="5"
                                value={loading ? 'Loading...' : value}
                                disabled={loading}
                                onChange={this.handleChange}
                            />
                        </Form.Group>
                        {error ? <div className="form-error">
                            {error}
                        </div> : ''}
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
                    </Form>}
                </div>
            </div>
        </Container>
    }
}
