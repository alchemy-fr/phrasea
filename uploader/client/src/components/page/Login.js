import React, {Component} from 'react';
import {Button, FormControl, FormGroup, FormLabel} from "react-bootstrap";
import {Link, Redirect} from "react-router-dom";
import auth from '../../auth';
import config from '../../config';
import Container from "../Container";
import Logo from "../Logo";
import OAuthProviders from "../oauth/OAuthProviders";
import {Translation} from "react-i18next";

export default class Login extends Component {
    state = {
        username: '',
        password: '',
        redirectToReferrer: false,
        error: null,
    };

    isFormValid() {
        return this.state.username.length > 0 && this.state.password.length > 0;
    }

    handleSubmit = event => {
        event.preventDefault();
        auth.login(this.state.username, this.state.password, () => {
            this.setState({
                redirectToReferrer: true,
            });
        }, (err, res) => {
            if (err) {
                throw err;
            } else if (res.body.error_description) {
                this.setState({
                    error: res.body.error_description,
                });
            }
        });
    };

    handleChange = event => {
        this.setState({
            [event.target.id]: event.target.value,
            error: null,
        });
    };

    render() {
        const {redirectToReferrer, error} = this.state;
        const {from} = this.props.location.state || {from: {pathname: '/'}};

        if (auth.isAuthenticated() || redirectToReferrer === true) {
            return <Redirect to={from}/>
        }

        return <Translation>
            {t => <>
                <Logo/>
                <Container title="Please sign in">
                    <div className="form-container login-form">
                        <form onSubmit={this.handleSubmit}>
                            <FormGroup controlId="username">
                                <FormLabel>
                                    {t('form.email.label')}
                                </FormLabel>
                                <FormControl
                                    autoFocus
                                    type="username"
                                    value={this.state.username}
                                    onChange={this.handleChange}
                                />
                            </FormGroup>
                            <FormGroup controlId="password">
                                <FormLabel>{t('form.password.label')}</FormLabel>
                                <FormControl
                                    value={this.state.password}
                                    onChange={this.handleChange}
                                    type="password"
                                />
                            </FormGroup>
                            {error ? <div className="error text-danger">{error}</div> : ''}
                            <Button
                                block
                                disabled={!this.isFormValid()}
                                type="submit"
                            >
                                {t('form.submit_button')}
                            </Button>
                        </form>

                        <p>
                            <Link to="/forgot-password">{t('login.forgot_password')}</Link>
                        </p>
                        {config.getSignUpURL() ?
                            <p>
                                {t('login.not_registered_yet')} <a target="_new" href={config.getSignUpURL()}>
                                {t('login.sign_up_link')}
                            </a>
                            </p> : ''}
                    </div>

                    <hr/>
                    <OAuthProviders/>
                </Container>
            </>
            }
        </Translation>;
    }
}
