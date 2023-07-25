import React, {useEffect} from 'react';
import config from '../../config';
import {useLocation} from "react-router-dom";
import {oauthClient} from "../../api/api-client";

export default function Login() {
    const {state} = useLocation() as {
        state?: {
            from?: string;
        };
    };

    useEffect(() => {
        if (!config.isDirectLoginForm()) {
            const from = state?.from;
            document.location.href = oauthClient.createAuthorizeUrl({
                connectTo: config.get('autoConnectIdP') || undefined,
                state: from ? btoa(JSON.stringify({r: from})) : undefined,
            });
        }
    }, []);

    return <div>Redirecting...</div>;
}
