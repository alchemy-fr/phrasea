import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import config from "../../lib/config";
import {oauthClient, setAuthRedirect} from "../../lib/oauth";
import {FormLayout, Login} from "react-ps";
import FullPageLoader from "../FullPageLoader";

class AuthenticationMethod extends PureComponent {
    static propTypes = {
        onAuthorization: PropTypes.func.isRequired,
        error: PropTypes.string,
    };

    componentDidMount() {
        setAuthRedirect(document.location.pathname);

        const autoConnectIdP = config.get('autoConnectIdP');
        if (autoConnectIdP) {
            document.location.href = oauthClient.createAuthorizeUrl({
                connectTo: autoConnectIdP || undefined,
                redirectPath: `/auth/${autoConnectIdP}`,
            });
        }
    }

    render() {
        if (config.get('autoConnectIdP')) {
            return <FullPageLoader/>
        }

        return <div className={'container'}>
            <FormLayout>
                <Login
                    {...config.get('loginFormLayout') || {}}
                    onLogin={() => {
                        console.log('onLogin');
                        this.props.onAuthorization();
                    }}
                    oauthClient={oauthClient}
                    providers={config.get('identityProviders')}
                    authBaseUrl={config.getAuthBaseUrl()}
                    authClientId={config.getClientCredential().clientId}
                />
            </FormLayout>
        </div>
    }
}

export default AuthenticationMethod;
