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
        baseSchema: PropTypes.object,
        validateForm: PropTypes.bool,
    };

    state = {
        schema: null,
    };

    async componentWillMount() {
        let schema = await config.getFormSchema();

        const {baseSchema} = this.props;

        if (baseSchema) {
            if (baseSchema.required) {
                schema.required = [
                    ...baseSchema.required,
                    ...schema.required,
                ];
            }

            if (baseSchema.properties) {
                schema.properties = {
                    ...baseSchema.properties,
                    ...schema.properties,
                };
            }
        }

        this.setState({
            schema
        });
    }

    onSubmit = (data) => {
        const accessToken = auth.getAccessToken();

        if (this.props.validateForm) {
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
        } else {
            this.props.onComplete(data);
        }
    };

    render() {
        const {schema} = this.state;

        if (!schema) {
            return 'Loading form...';
        }

        return <div className="form-container">
            <AssetLiForm
                schema={schema}
                onSubmit={this.onSubmit}
            />
        </div>;
    }
}
