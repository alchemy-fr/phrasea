import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import config from "../../lib/config";
import {oauthClient} from "../../lib/oauth";
import {FormLayout} from "react-ps";
import FullPageLoader from "../FullPageLoader";

function createLoginUrl() {
    const autoConnectIdP = config.get('autoConnectIdP');

    return oauthClient.createAuthorizeUrl({
        connectTo: autoConnectIdP || undefined,
        redirectPath: document.location.toString(),
    });
}

class AuthenticationMethod extends PureComponent {
    static propTypes = {
        onAuthorization: PropTypes.func.isRequired,
        error: PropTypes.string,
    };

    render() {
        if (config.get('autoConnectIdP')) {
            return <FullPageLoader/>
        }

        return <div className={'container'}>
            <FormLayout>
                <a
                    href={createLoginUrl()}
                >Login</a>
            </FormLayout>
        </div>
    }
}

export default AuthenticationMethod;
