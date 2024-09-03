import React, {Component} from 'react';
import '../scss/Upload.scss';
// import PropTypes from "prop-types";
import AssetLiForm from './AssetLiForm';
import {SubmissionError} from 'redux-form';
import {Translation} from 'react-i18next';
import {getFormSchema} from '../requests';
import apiClient from '../lib/apiClient';

export default class AssetForm extends Component {
    // static propTypes = {
    //     onComplete: PropTypes.func,
    //     onCancel: PropTypes.func,
    //     baseSchema: PropTypes.object,
    //     submitPath: PropTypes.string.isRequired,
    //     targetId: PropTypes.string.isRequired,
    // };

    state = {
        schema: undefined,
    };

    componentDidMount() {
        this.init();
    }

    async init() {
        const {baseSchema, targetId} = this.props;
        let schema = await getFormSchema(targetId);
        if (null === schema) {
            if (!baseSchema) {
                this.props.onComplete({});
                return;
            }
            schema = {};
        } else {
            schema = schema.data;
        }

        if (baseSchema) {
            if (baseSchema.required) {
                schema.required = [
                    ...baseSchema.required,
                    ...(schema.required || []),
                ];
            }

            if (baseSchema.properties) {
                schema.properties = {
                    ...baseSchema.properties,
                    ...(schema.properties || {}),
                };
            }
        }

        this.setState({schema});
    }

    onSubmit = async reduxFormData => {
        let formData = {...reduxFormData};
        const {baseSchema, submitPath, onComplete} = this.props;

        // Extract base fields out from form data
        let data = {
            target: `/targets/${this.props.targetId}`,
        };
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

        let r;
        try {
            r = (await apiClient.post(submitPath, data)).data;
        } catch (e) {
            console.debug(e);
            throw new SubmissionError({_error: e.toString()});
        }

        if (r.errors && Object.keys(r.errors).length > 0) {
            const {errors} = r;
            const errs = {};

            Object.keys(errors).forEach(i => {
                errs[i] = errors[i].join('\n');
            });

            throw new SubmissionError(errs);
        }

        onComplete && onComplete(formData);
    };

    render() {
        const {schema} = this.state;

        if (undefined === schema) {
            return (
                <Translation>
                    {t => t('layout.loading_form', 'Loading form…')}
                </Translation>
            );
        }

        return (
            <div className="form-container">
                <AssetLiForm
                    schema={schema}
                    onSubmit={this.onSubmit}
                    onCancel={this.props.onCancel || null}
                />
            </div>
        );
    }
}
