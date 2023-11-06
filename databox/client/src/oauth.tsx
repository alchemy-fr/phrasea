import React, {useEffect} from "react";
import {useNavigate} from "react-router-dom";
import {getPath} from "./routes";
import {toast} from "react-toastify";
import {oauthClient} from "./api/api-client";

export default function OAuthRedirect() {
    const navigate = useNavigate();

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const state = params.get('state');

        oauthClient
            .getTokenFromAuthCode(
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
