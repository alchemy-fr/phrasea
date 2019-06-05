import React, {Component} from 'react';
import '../../scss/Upload.scss';
import PropTypes from "prop-types";
import AssetForm from "../AssetForm";
import config from "../../config";
import auth from "../../auth";
import request from "superagent";

export default class UploadForm extends Component {
    constructor(props) {
        super(props);

        this.state = {
            schema: null,
        };
    }

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

                this.props.onNext(data);
            });
    };

    render() {
        const {files} = this.props;
        const {schema} = this.state;

        return (
            <div className="container">
                <div className="App">
                    <header>
                        <h1>Uploader.</h1>
                    </header>
                    <p>
                        {files.length} selected files.
                    </p>

                    {schema ?
                    <AssetForm
                        schema={schema}
                        onSubmit={this.onSubmit} />
                    : 'Loading form...'}
                </div>
            </div>
        );
    }
}

UploadForm.propTypes = {
    files: PropTypes.array.isRequired,
    onNext: PropTypes.func.isRequired,
};

