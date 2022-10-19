import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import config from "../../lib/config";
import {oauthClient, setAuthRedirect} from "../../lib/oauth";
import {Login} from "react-ps";

class AuthenticationMethod extends PureComponent {
    static propTypes = {
        onAuthorization: PropTypes.func.isRequired,
        error: PropTypes.string,
    };

    componentDidMount() {
        setAuthRedirect(document.location.pathname);
    }

    render() {
        return <div className={'container'}>
            <Login
                onLogin={() => {
                    console.log('onLogin');
                    this.props.onAuthorization();
                }}
                oauthClient={oauthClient}
                providers={config.get('identityProviders')}
                authBaseUrl={config.getAuthBaseUrl()}
                authClientId={config.getClientCredential().clientId}
            />
        </div>
    }
}

export default AuthenticationMethod;
