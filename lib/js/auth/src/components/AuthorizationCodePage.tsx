import useEffectOnce from "@alchemy/react-hooks/src/useEffectOnce";
import OAuthClient from "../client/OAuthClient";
import {useNavigate} from "react-router-dom";
import React from "react";
import {useAuth} from "../hooks/useAuth";

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
    const urlParams = new URLSearchParams(window.location.search);
    const {setTokens} = useAuth();

    useEffectOnce(() => {
        const code = urlParams.get('code');
        if (!code) {
            setError(`Missing authorization code.`);

            return;
        }

        const state = urlParams.get('state');

        oauthClient.getTokenFromAuthCode(
            code,
            window.location.href.split('?')[0]
        )
            .then((tokens) => {
                setTokens(tokens);
                if (successHandler) {
                    successHandler();

                    return;
                }

                if (state) {
                    try {
                        const dState = JSON.parse(atob(state)) as {
                            r?: string;
                        };
                        // eslint-disable-next-line no-prototype-builtins
                        if (
                            typeof dState === 'object' &&
                            // eslint-disable-next-line
                            dState.hasOwnProperty('r') &&
                            typeof dState.r === 'string'
                        ) {
                            navigate(dState.r);

                            return;
                        }
                    } catch (e: any) {
                        // Ignore
                    }
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
