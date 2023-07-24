import React, {useEffect} from "react";
import config from "./config";
import {useNavigate} from "react-router-dom";
import {getPath} from "./routes";
import {toast} from "react-toastify";
import {OAuthClient} from "react-ps";

export const oauthClient = new OAuthClient({
    clientId: config.getClientId(),
    baseUrl: config.getAuthBaseUrl(),
});

export default function OAuthRedirect() {
    const navigate = useNavigate();

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const state = params.get('state');

        oauthClient
            .getAccessTokenFromAuthCode(
                params.get('code') as string,
                window.location.href.split('?')[0]
            )
            .then(() => {
                if (state) {
                    const dState = JSON.parse(atob(state));
                    if (typeof dState === 'object' && dState.hasOwnProperty('r')) {
                        navigate(dState.r);

                        return;
                    }
                }
                navigate(getPath('app'));
            }, (e: Error) => {
                toast.error(e.toString());
                navigate(getPath('app'));
            })
    }, []);

    return null;
}
