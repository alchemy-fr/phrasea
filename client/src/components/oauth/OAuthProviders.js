import React, {Component} from 'react';
import config from "../../config";

const authConfig = window.config.auth;

const host = [
    window.location.protocol,
    '//',
    window.location.hostname,
].join('');


export default class OAuthProviders extends Component {
    render() {
        return (
            <div>
                {authConfig.oauth_providers.map((provider) => {
                    const redirectUri = `${host}/auth/${provider.name}`;
                    const authorizeUrl = `${window.config._env_.AUTH_BASE_URL}/oauth/${provider.name}/authorize?redirect_uri=${encodeURIComponent(redirectUri)}&client_id=${config.getClientCredential().clientId}`;

                    return <div
                        key={provider.name}
                    >
                        <a href={authorizeUrl}>Connect to {provider.title}</a>
                    </div>
                })}
            </div>
        );
    }
}
