import useEffectOnce from "@alchemy/react-hooks/src/useEffectOnce";
import {OAuthClient} from "@alchemy/auth";
import {useNavigate} from "react-router-dom";
import {useAuth} from "../hooks/useAuth";
import {toast} from "react-toastify";
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
    const urlParams = new URLSearchParams(window.location.search);
    const {setTokens} = useAuth();
    const [error, setError] = React.useState<any>();

    useEffectOnce(() => {
        const code = urlParams.get('code');
        if (!code) {
            setError(new Error(`Missing authorization code.`));

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

                setError(e);
            });
    }, []);

    React.useEffect(() => {
        if (error) {
            console.error(error);
            toast.warn(error.toString());
        }
    }, [error]);

    if (error) {
        throw error;
    }

    return <></>
}
