import React, {Component} from 'react';
import {Button, FormGroup, FormControl, FormLabel} from "react-bootstrap";
import config from "../../config";
import request from "superagent";
import Container from "../Container";
import i18n from "../../locales/i18n";

export default class ResetPassword extends Component {
    constructor(props) {
        super(props);

        this.state = {
            username: '',
            requested: false,
        };
    }

    isFormValid() {
        return this.state.username.length > 0;
    }

    handleSubmit = async event => {
        event.preventDefault();

        this.setState({requested: true});

        await request
            .post(`${config.getAuthBaseURL()}/${i18n.language}/password/reset-request`)
            .accept('json')
            .send({
                username: this.state.username,
            })
        ;

    };

    handleChange = event => {
        this.setState({
            [event.target.id]: event.target.value
        });
    };

    render() {
        const {requested} = this.state;

        return (
            <Container title="Reset password">
                <div>
                    {requested ? 'You will receive an email to reset your password.' :
                        <form onSubmit={this.handleSubmit}>
                            <FormGroup controlId="username">
                                <FormLabel>Email</FormLabel>
                                <FormControl
                                    autoFocus
                                    type="email"
                                    value={this.state.username}
                                    onChange={this.handleChange}
                                />
                            </FormGroup>
                            <Button
                                block
                                disabled={!this.isFormValid()}
                                type="submit"
                            >
                                Request password reset
                            </Button>
                        </form>
                    }
                </div>
            </Container>
        );
    }
}
