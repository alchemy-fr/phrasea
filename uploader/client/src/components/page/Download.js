import React, {Component} from 'react';
import '../../scss/Upload.scss';
import Container from "../Container";
import {Link} from "react-router-dom";
import AssetForm from "../AssetForm";

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

    onComplete = () => {
        this.setState({
            done: true,
        });
    };

    onCancel = () => {
        this.props.history.push('/');
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
                        submitPath={'/downloads'}
                        baseSchema={this.baseSchema}
                        onComplete={this.onComplete}
                        onCancel={this.onCancel}
                    />
                }
            </Container>
        );
    }
}
