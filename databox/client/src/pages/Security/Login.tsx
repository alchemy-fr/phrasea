import React, {useEffect} from 'react';
import config from '../../config';
import {oauthClient} from "../../oauth";
import {useLocation} from "react-router-dom";

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
                state: from ? JSON.stringify({r: from}) : undefined,
            });
        }
    }, []);

    return <div>Redirecting...</div>;
}
