import React, {Component} from 'react';
import '../scss/Upload.scss';
import PropTypes from "prop-types";
import config from "../config";
import auth from "../auth";
import request from "superagent";
import AssetLiForm from "./AssetLiForm";

export default class AssetForm extends Component {
    static propTypes = {
        onComplete: PropTypes.func.isRequired,
    };

    state = {
        schema: null,
    };

    async componentWillMount() {
        let schema = await config.getFormSchema();

        this.setState({
            schema
        });
    }

    onSubmit = (data) => {
        console.debug('data', data);

        const accessToken = auth.getAccessToken();

        request
            .post(config.getUploadBaseURL() + '/form/validate')
            .accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .send({data})
            .end((err, res) => {
                if (!auth.isResponseValid(err, res)) {
                    return;
                }

                if (Object.keys(res.body.errors).length > 0) {
                    alert(JSON.stringify(res.body.errors));
                    return;
                }

                this.props.onComplete(data);
            });
    };

    render() {
        const {schema} = this.state;

        if (!schema) {
            return 'Loading form...';
        }

        return <AssetLiForm
            schema={schema}
            onSubmit={this.onSubmit}
        />;
    }
}
