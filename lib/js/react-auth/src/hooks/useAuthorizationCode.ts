import useEffectOnce from '@alchemy/react-hooks/src/useEffectOnce'
import React from "react";
import {useAuth} from "./useAuth";
import {OAuthClient} from "@alchemy/auth";

type Props = {
    navigate: (path: string, options?: {
        replace?: boolean;
    }) => void;
    oauthClient: OAuthClient,
    successUri?: string,
    successHandler?: () => void,
    errorHandler?: (e: any) => void,
};

export type {Props as UseAuthorizationCodeProps};

export function useAuthorizationCode({
    oauthClient,
    successHandler,
    successUri,
    errorHandler,
    navigate,
    allowNoCode,
}: {
    allowNoCode?: boolean;
} & Props) {
    const {setTokens} = useAuth();
    const [error, setError] = React.useState<any>();
    const urlParams = new URLSearchParams(window.location.search);
    const code = urlParams.get('code');

    useEffectOnce(() => {
        const code = urlParams.get('code');
        if (!code) {
            if (!allowNoCode) {
                setError(new Error(`Missing authorization code.`));
            }

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

    return {
        error,
        hasCode: Boolean(code),
    }
}
