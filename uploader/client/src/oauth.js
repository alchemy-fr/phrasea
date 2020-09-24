import React, {PureComponent} from "react";
import {OAuthClient, OAuthRedirect as BaseOAuthRedirect} from "@alchemy-fr/phraseanet-react-components";
import config from "./config";

const {clientId, clientSecret} = config.getClientCredential();

export const oauthClient = new OAuthClient({
    clientId,
    clientSecret,
    baseUrl: config.getAuthBaseUrl(),
});

export class OAuthRedirect extends PureComponent
{
    render() {
        return <BaseOAuthRedirect
            {...this.props}
            oauthClient={oauthClient}
            errorUri={'/auth-error'}
        />
    }
}
