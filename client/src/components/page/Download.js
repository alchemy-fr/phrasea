import React, {Component} from 'react';
import '../../scss/Upload.scss';
import Container from "../Container";
import {Link} from "react-router-dom";
import AssetForm from "../AssetForm";
import request from "superagent";
import config from "../../config";
import auth from "../../auth";

export default class Download extends Component {
    state = {
        done: false,
    };

    baseSchema = {
        "required": [
            "url",
        ],
        "properties": {
            "url": {
                'title': 'Asset URL',
                'type': 'string',
                'widget': 'url'
            }
        }
    };

    onComplete = (formData) => {
        const accessToken = auth.getAccessToken();

        request
            .post(config.getUploadBaseURL() + '/downloads')
            .accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .send({
                formData,
            })
            .end((err, res) => {
                auth.isResponseValid(err, res);
                this.setState({
                    done: true,
                })
            });
    };

    handleChange = (url, isURLValid) => {
        this.setState({
            url,
            isURLValid
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
                    <AssetForm
                        validateForm={true}
                        baseSchema={this.baseSchema}
                        onComplete={this.onComplete}
                        submitDisabled={!this.state.isURLValid}
                    />
                }
            </Container>
        );
    }
}
