import useEffectOnce from "@alchemy/react-hooks/src/useEffectOnce";
import OAuthClient from "../client/OAuthClient";
import {useNavigate} from "react-router-dom";
import React from "react";

type Props = {
    oauthClient: OAuthClient,
    successUri?: string,
    successHandler?: () => void,
    errorHandler?: (e: any) => void,
};

export default function AuthorizationCodePage({
    oauthClient,
    successUri,
    successHandler,
    errorHandler,
}: Props) {
    const navigate = useNavigate();
    const [error, setError] = React.useState<string | undefined>();

    useEffectOnce(() => {
        const code = getQueryParam('code');
        if (!code) {
            setError(`Missing authorization code.`);

            return;
        }

        oauthClient.getTokenFromAuthCode(
            code,
            window.location.href.split('?')[0]
        )
            .then(() => {
                if (successHandler) {
                    successHandler();

                    return;
                }

                navigate(successUri ?? '/', {replace: true});
            })
            .catch ((e) => {
                if (errorHandler) {
                    errorHandler(e);

                    return ;
                }

                console.error(e);
                setError(e.toString());
            });
    }, []);

    if (error) {
        return <div style={{
            color: 'red',
        }}>
            {error}
        </div>
    }

    return <></>
}

function getQueryParam(name: string): string | undefined {
    const urlParams = new URLSearchParams(window.location.search);

    return urlParams.get(name) || undefined;
}
