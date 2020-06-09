import React, {Component} from 'react';
import '../scss/Upload.scss';
import PropTypes from "prop-types";
import config from "../config";
import request from "superagent";
import AssetLiForm from "./AssetLiForm";
import {SubmissionError} from 'redux-form';
import {Translation} from "react-i18next";
import {oauthClient} from "../oauth";
import {getFormSchema} from "../requests";

export default class AssetForm extends Component {
    static propTypes = {
        onComplete: PropTypes.func,
        onCancel: PropTypes.func,
        baseSchema: PropTypes.object,
        submitPath: PropTypes.string.isRequired,
    };

    state = {
        schema: null,
    };

    componentDidMount() {
        this.init();
    }

    async init() {
        const schema = await getFormSchema();
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

        schema.properties = {
            ...schema.properties,
            ...{
                __notify_email: {
                    title: 'Notify me when done!',
                    type: 'boolean',
                }
            },
        };

        this.setState({schema});
    }

    onSubmit = async (reduxFormData) => {
        let formData = {...reduxFormData};
        const accessToken = oauthClient.getAccessToken();
        const {baseSchema, submitPath, onComplete} = this.props;

        // Extract base fields out from form data
        let data = {};
        if (baseSchema && baseSchema.properties) {
            Object.keys(baseSchema.properties).forEach(key => {
                if (formData.hasOwnProperty(key)) {
                    data[key] = formData[key];
                    delete formData[key];
                }
            });
        }
        data = {
            ...data,
            data: formData,
        };

        return new Promise((resolve, reject) => {
            request
                .post(config.getUploadBaseURL() + submitPath)
                .accept('json')
                .set('Authorization', `Bearer ${accessToken}`)
                .send(data)
                .end((err, res) => {
                    if (!oauthClient.isResponseValid(err, res)) {
                        console.log(err);
                        reject(new SubmissionError({_error: err.toString()}));
                        return;
                    }

                    if (res.body.errors && Object.keys(res.body.errors).length > 0) {
                        const {errors} = res.body;
                        const errs = {};

                        Object.keys(errors).forEach((i) => {
                            errs[i] = errors[i].join("\n");
                        });

                        reject(new SubmissionError(errs));
                        return;
                    }

                    onComplete && onComplete(formData);
                    resolve();
                });

        });
    };

    render() {
        const {schema} = this.state;

        if (!schema) {
            return <Translation>
                {t => t('layout.loading_form')}
            </Translation>;
        }

        return <div className="form-container">
            <AssetLiForm
                schema={schema}
                onSubmit={this.onSubmit}
                onCancel={this.props.onCancel || null}
            />
        </div>;
    }
}
