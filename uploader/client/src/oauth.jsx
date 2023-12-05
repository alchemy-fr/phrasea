import React, {PureComponent} from 'react';
import config from './config';
import {KeycloakClient} from '@alchemy/auth';
// import PropTypes from "prop-types";
import FullPageLoader from './components/FullPageLoader';

export const keycloakClient = new KeycloakClient({
    clientId: config.clientId,
    baseUrl: config.keycloakUrl,
    realm: config.realmName,
});

export const oauthClient = keycloakClient.client;

export class OAuthRedirect extends PureComponent {
    // static propTypes = {
    //     history: PropTypes.object.isRequired,
    //     location: PropTypes.object.isRequired,
    //     successUri: PropTypes.string,
    //     errorHandler: PropTypes.func,
    // };

    handleSuccess = () => {
        const {history, successHandler} = this.props;

        if (successHandler) {
            return successHandler(history);
        }

        history.push('/');
    };

    handleError = e => {
        const {history, errorHandler} = this.props;

        if (errorHandler) {
            return errorHandler(e, history);
        }

        console.error(e);
        alert(e);
        history.push('/auth-error');
    };

    componentDidMount() {
        oauthClient
            .getTokenFromAuthCode(
                this.getCode(),
                window.location.href.split('?')[0]
            )
            .then(this.handleSuccess, this.handleError);
    }

    getCode() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('code');
    }

    render() {
        return <FullPageLoader />;
    }
}
