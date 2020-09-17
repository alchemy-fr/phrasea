import React, {Component} from 'react';
import {Button, FormControl, FormGroup, FormLabel} from "react-bootstrap";
import {Link, Redirect} from "react-router-dom";
import config from '../../config';
import Container from "../Container";
import Logo from "../Logo";
import {Translation} from "react-i18next";
import {OAuthProviders} from "@alchemy-fr/phraseanet-react-components";
import {oauthClient} from "../../oauth";

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

        this.setState({submitting: true}, async () => {
            try {
                await oauthClient.login(this.state.username, this.state.password);
                this.setState({
                    submitting: false,
                    redirectToReferrer: true,
                });
            } catch (e) {
                this.setState({submitting: false});
                if (e.res && e.res.body.error_description) {
                    this.setState({
                        error: e.res.body.error_description,
                    });
                } else {
                    throw e;
                }
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
        const {redirectToReferrer, error, submitting} = this.state;
        const {from} = this.props.location.state || {from: {pathname: '/'}};

        if (oauthClient.isAuthenticated() || redirectToReferrer === true) {
            return <Redirect to={from}/>
        }

        const host = [
            window.location.protocol,
            '//',
            window.location.hostname,
        ].join('');

        const url = `${config.getAuthBaseUrl()}/oauth/v2/auth?response_type=code&client_id=${config.getClientCredential().clientId}&redirect_uri=${host}/auth`;

        document.location.href = url;

        // return <Translation>
        //     {t => <>
        //         <Logo/>
        //         <Container title="Please sign in">
        //             <div className="form-container login-form">
        //                 <form onSubmit={this.handleSubmit}>
        //                     <FormGroup controlId="username">
        //                         <FormLabel>
        //                             {t('form.email.label')}
        //                         </FormLabel>
        //                         <FormControl
        //                             disabled={submitting}
        //                             autoFocus
        //                             type="username"
        //                             value={this.state.username}
        //                             onChange={this.handleChange}
        //                         />
        //                     </FormGroup>
        //                     <FormGroup controlId="password">
        //                         <FormLabel>{t('form.password.label')}</FormLabel>
        //                         <FormControl
        //                             disabled={submitting}
        //                             value={this.state.password}
        //                             onChange={this.handleChange}
        //                             type="password"
        //                         />
        //                     </FormGroup>
        //                     {error ? <div className="error text-danger">{error}</div> : ''}
        //                     <Button
        //                         block
        //                         disabled={!this.isFormValid() || submitting}
        //                         type="submit"
        //                     >
        //                         {t('form.submit_button')}
        //                     </Button>
        //                 </form>
        //
        //                 <p>
        //                     <Link to="/forgot-password">{t('login.forgot_password')}</Link>
        //                 </p>
        //                 {config.getSignUpURL() ?
        //                     <p>
        //                         {t('login.not_registered_yet')} <a target="_new" href={config.getSignUpURL()}>
        //                         {t('login.sign_up_link')}
        //                     </a>
        //                     </p> : ''}
        //             </div>
        //
        //             <OAuthProviders
        //                 authBaseUrl={config.getAuthBaseUrl()}
        //                 authClientId={config.getClientCredential().clientId}
        //                 providers={config.get('identityProviders')}
        //             />
        //         </Container>
        //     </>
        //     }
        // </Translation>;
    }
}
