import React, {Component} from 'react';
import { Button, FormGroup, FormControl, FormLabel } from "react-bootstrap";
import config from "../../config";
import request from "superagent";
import auth from "../../auth";

export default class ChangePassword extends Component {
    constructor(props) {
        super(props);

        this.state = {
            oldPassword: '',
            newPassword: '',
            repeatPassword: '',
        };
    }

    isFormValid() {
        return this.state.oldPassword.length > 0
            && this.state.newPassword.length > 0
            && this.state.repeatPassword.length > 0
            && this.state.newPassword === this.state.repeatPassword
            ;
    }

    handleSubmit = async event => {
        event.preventDefault();

        let response = await request
            .post(config.getAuthBaseURL() + '/password/change')
            .set('accept', 'json')
            .set('Authorization', 'Bearer ' + auth.getAccessToken())
            .send({
                old_password: this.state.oldPassword,
                new_password: this.state.newPassword,
            })
        ;

        if (response) {

        }
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

        if (this.state.repeatPassword && this.state.newPassword
        && this.state.newPassword !== this.state.repeatPassword) {
            errors.push('Passwords mismatch');
        }

        return (
            <div>
                <h2>Change password</h2>
                <div>
                    <form onSubmit={this.handleSubmit}>
                        <FormGroup controlId="oldPassword">
                            <FormLabel>Old password</FormLabel>
                            <FormControl
                                autoFocus
                                type="password"
                                value={this.state.oldPassword}
                                onChange={this.handleChange}
                            />
                        </FormGroup>
                        <FormGroup controlId="newPassword">
                            <FormLabel>New password</FormLabel>
                            <FormControl
                                autoFocus
                                type="password"
                                value={this.state.newPassword}
                                onChange={this.handleChange}
                            />
                        </FormGroup>
                        <FormGroup controlId="repeatPassword">
                            <FormLabel>Retype new password</FormLabel>
                            <FormControl
                                autoFocus
                                type="password"
                                value={this.state.repeatPassword}
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
                    </form>
                </div>
            </div>
        );
    }
}
