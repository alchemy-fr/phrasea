import React, {useEffect} from 'react';
import config from '../../config';
import {oauthClient} from "../../oauth";

export default function Login() {
    useEffect(() => {
        if (!config.isDirectLoginForm()) {
            document.location.href = oauthClient.createAuthorizeUrl({
                connectTo: config.get('autoConnectIdP') || undefined,
            });
        }
    }, []);

    return <div>Redirecting...</div>;
}
