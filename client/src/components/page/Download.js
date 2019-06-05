import React, {Component} from 'react';
import '../../scss/Upload.scss';
import Container from "../Container";
import {Link} from "react-router-dom";
import AssetForm from "../AssetForm";
import request from "superagent";
import config from "../../config";
import auth from "../../auth";
import URLInput from "../URLInput";

export default class Download extends Component {
    state = {
        url: '',
        done: false,
    };

    onComplete = (formData) => {
        let {url} = this.state;
        const accessToken = auth.getAccessToken();

        request
            .post(config.getUploadBaseURL() + '/downloads')
            .accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .send({
                url,
                formData,
            })
            .end((err, res) => {
                auth.isResponseValid(err, res);
                this.setState({
                    done: true,
                })
            });
    };

    handleChange = (url) => {
        this.setState({
            url,
        });
    };

    render() {
        const {done} = this.state;

        return (
            <Container>
                <div>
                    <Link to="/">Back</Link>
                </div>

                {done ? <h3>Your file will be downloaded!</h3> :
                    <div className="form-container">
                        <URLInput
                            onChange={this.handleChange}
                        />
                        <AssetForm
                            onComplete={this.onComplete}
                        />
                    </div>
                }
            </Container>
        );
    }
}
