import React, {Component} from 'react';
import config from "../../config";

const host = [
    window.location.protocol,
    '//',
    window.location.hostname,
].join('');


export default class OAuthProviders extends Component {
    render() {
        return (
            <div>
                {config.get('identityProviders').map((provider) => {
                    const redirectUri = `${host}/auth/${provider.name}`;
                    const authorizeUrl = `${config.getAuthBaseURL()}/oauth/${provider.name}/authorize?redirect_uri=${encodeURIComponent(redirectUri)}&client_id=${config.getClientCredential().clientId}`;

                    return <div
                        key={provider.name}
                    >
                        <a href={authorizeUrl}>Connect with {provider.title}</a>
                    </div>
                })}
            </div>
        );
    }
}
