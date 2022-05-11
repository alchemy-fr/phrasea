import React, {useEffect} from 'react';
import config from '../../config';
import {createAuthorizeUrl} from "@alchemy-fr/phraseanet-react-components/dist/oauth/funcs";

export default function Login() {
    useEffect(() => {
        if (!config.isDirectLoginForm()) {
            document.location.href = createAuthorizeUrl(config.getAuthBaseUrl(), config.getClientCredential().clientId);
        }
    }, []);

    return <div>Redirecting...</div>;
}
