import React, {useEffect} from "react";
import config from "./config";
import {useNavigate} from "react-router-dom";
import {getPath} from "./routes";
import {toast} from "react-toastify";
import {OAuthClient} from "react-ps";

const {clientId, clientSecret} = config.getClientCredential();

export const oauthClient = new OAuthClient({
    clientId,
    clientSecret,
    baseUrl: config.getAuthBaseUrl(),
});

export default function OAuthRedirect() {
    const navigate = useNavigate();

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);

        oauthClient
            .getAccessTokenFromAuthCode(
                params.get('code') as string,
                window.location.href.split('?')[0]
            )
            .then(() => {
                navigate(getPath('app'));
            }, (e: Error) => {
                toast.error(e.toString());
                navigate(getPath('app'));
            })
    }, []);

    return null;
}
