import React, {Component} from 'react';
import {Button, FormGroup, FormControl, FormLabel} from "react-bootstrap";
import config from "../../config";
import request from "superagent";
import auth from "../../auth";

export default class ChangePassword extends Component {
    state = {
        oldPassword: '',
        newPassword: '',
        repeatPassword: '',
        changed: false,
        error: null,
    };

    isFormValid() {
        const {
            repeatPassword,
            newPassword,
            oldPassword,
        } = this.state;

        return oldPassword.length > 0
            && newPassword.length > 0
            && repeatPassword.length > 0
            && newPassword === repeatPassword
            ;
    }

    handleSubmit = event => {
        event.preventDefault();

        const {
            newPassword,
            oldPassword,
        } = this.state;

        request
            .post(config.getAuthBaseURL() + '/password/change')
            .accept('json')
            .set('Authorization', 'Bearer ' + auth.getAccessToken())
            .send({
                old_password: oldPassword,
                new_password: newPassword,
            })
            .end((err, res) => {
                if (!auth.isResponseValid(err, res)) {
                    if (res.body.error_description) {
                        this.setState({error: res.body.error_description})
                    }
                    return;
                }

                auth.doLogin(auth.getUsername(), newPassword);

                this.setState({
                    changed: true,
                });
            });

    };

    handleChange = event => {
        this.setState({
            [event.target.id]: event.target.value
        });
    };

    renderErrors = (errors) => {
        return <ul>
            {errors.map((error, key) => {
                return <li key={key}>{error}</li>;
            })}
        </ul>;
    };

    render() {
        let errors = [];

        const {
            repeatPassword,
            newPassword,
            oldPassword,
            error,
            changed,
        } = this.state;

        if (repeatPassword && newPassword
            && newPassword !== repeatPassword) {
            errors.push('Passwords mismatch');
        }
        if (error) {
            errors.push(error);
        }

        return (
            <div>
                <h2>Change password</h2>
                <div>
                    {!changed ?
                        <form onSubmit={this.handleSubmit}>
                            <FormGroup controlId="oldPassword">
                                <FormLabel>Old password</FormLabel>
                                <FormControl
                                    autoFocus
                                    type="password"
                                    value={oldPassword}
                                    onChange={this.handleChange}
                                />
                            </FormGroup>
                            <FormGroup controlId="newPassword">
                                <FormLabel>New password</FormLabel>
                                <FormControl
                                    autoFocus
                                    type="password"
                                    value={newPassword}
                                    onChange={this.handleChange}
                                />
                            </FormGroup>
                            <FormGroup controlId="repeatPassword">
                                <FormLabel>Retype new password</FormLabel>
                                <FormControl
                                    autoFocus
                                    type="password"
                                    value={repeatPassword}
                                    onChange={this.handleChange}
                                />
                            </FormGroup>
                            {errors.length > 0 ? this.renderErrors(errors) : ''}

                            <Button
                                block
                                disabled={!this.isFormValid()}
                                type="submit"
                            >
                                Change password
                            </Button>
                        </form> : 'Password changed'}
                </div>
            </div>
        );
    }
}
