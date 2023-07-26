import React from 'react';
import config from "../../lib/config";
import {FormLayout} from "react-ps";
import {setAuthRedirect} from "../../lib/oauth";
import {oauthClient} from "../../lib/api-client";

type Props = {};

function createLoginUrl(): string {
    const autoConnectIdP = config.get('autoConnectIdP');

    setAuthRedirect(document.location.pathname);

    return oauthClient.createAuthorizeUrl({
        connectTo: autoConnectIdP || undefined,
    });
}

export default function AuthenticationMethod({}: Props) {
    return <div className={'container'}>
        <FormLayout>
            <div style={{
                textAlign: 'center',
            }}>

            <h3>
                This publication requires authentication.
            </h3>
            <a
                className={'btn btn-primary'}
                href={createLoginUrl()}
            >Login</a>
            </div>
        </FormLayout>
    </div>
}
