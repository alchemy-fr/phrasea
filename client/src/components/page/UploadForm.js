import React, {Component} from 'react';
import '../../scss/Upload.scss';
import PropTypes from "prop-types";
import AssetForm from "../AssetForm";
import config from "../../store/config";
import auth from "../../store/auth";
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

    onSubmit = async (data) => {
        console.debug('data', data);

        const accessToken = auth.getAccessToken();

        let response = await request
            .post(config.getUploadBaseURL() + '/form/validate')
            .accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .send({data})
        ;

        if (Object.keys(response.body.errors).length > 0) {
            alert(JSON.stringify(response.body.errors));
            return;
        }

        this.props.onNext(data);
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

