import React, {Component} from 'react';
import { Button, FormGroup, FormControl, FormLabel } from "react-bootstrap";
import config from "../../config";
import request from "superagent";

export default class ResetPassword extends Component {
    constructor(props) {
        super(props);

        this.state = {
            email: '',
        };
    }

    isFormValid() {
        return this.state.email.length > 0;
    }

    handleSubmit = async event => {
        event.preventDefault();

        let response = await request
            .post(config.getAuthBaseURL() + '/password/reset-request')
            .set('accept', 'json')
            .send({
                email: this.state.email,
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

    render() {
        return (
            <div className="container">
                <h1>Reset password</h1>
                <div>
                    <form onSubmit={this.handleSubmit}>
                        <FormGroup controlId="email">
                            <FormLabel>Email</FormLabel>
                            <FormControl
                                autoFocus
                                type="email"
                                value={this.state.email}
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
                </div>
            </div>
        );
    }
}
