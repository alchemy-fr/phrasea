import React, {Component} from 'react';
import {Link, Redirect} from "react-router-dom";
import config from '../../config';
import Container from "../Container";
import Logo from "../Logo";
import {Translation} from "react-i18next";
import {oauthClient} from "../../oauth";
import {FormLayout, Login as LoginComponent} from "react-ps";

export default class Login extends Component {
    state = {
        redirectToReferrer: false,
    };

    render() {
        const {redirectToReferrer} = this.state;
        const {from} = this.props.location.state || {from: {pathname: '/'}};

        if (oauthClient.isAuthenticated() || redirectToReferrer === true) {
            return <Redirect to={from}/>
        }

        if (!config.isDirectLoginForm()) {
            document.location.href = oauthClient.createAuthorizeUrl({
                connectTo: config.get('autoConnectIdP') || undefined,
            });

            return '';
        }

        return <Translation>
            {t => <>
                <Logo/>
                <Container title="Please sign in">
                    <FormLayout>
                        <LoginComponent
                            {...config.get('loginFormLayout') || {}}
                            onLogin={() => {
                                console.log('onLogin');
                                this.setState({
                                    redirectToReferrer: true,
                                });
                            }}
                            oauthClient={oauthClient}
                            providers={config.get('identityProviders')}
                            authBaseUrl={config.getAuthBaseUrl()}
                            authClientId={config.getClientCredential().clientId}
                        />

                        <p>
                            <Link to="/forgot-password">{t('login.forgot_password')}</Link>
                        </p>

                        {config.getSignUpURL() ?
                            <p>
                                {t('login.not_registered_yet')} <a target="_new" href={config.getSignUpURL()}>
                                {t('login.sign_up_link')}
                            </a>
                            </p> : ''}
                    </FormLayout>
                </Container>
            </>
            }
        </Translation>;
    }
}
