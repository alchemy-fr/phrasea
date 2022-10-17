import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import config from "../../lib/config";
import {setAuthRedirect} from "../../lib/oauth";
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
        const {clientId, clientSecret} = config.getClientCredential();

        return <div className={'container'}>
            <Login
                onLogin={() => {
                    console.log('onLogin');
                    this.props.onAuthorization();
                }}
                clientId={clientId}
                clientSecret={clientSecret}
                providers={config.get('identityProviders')}
                authBaseUrl={config.getAuthBaseUrl()}
                authClientId={config.getClientCredential().clientId}
            />
        </div>
    }
}

export default AuthenticationMethod;
