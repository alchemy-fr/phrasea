import useEffectOnce from '@alchemy/react-hooks/src/useEffectOnce';
import {useState} from 'react';
import {
    AuthConstant,
    decodeState,
    getPathFromRedirectUri,
    OAuthClient,
} from '@alchemy/auth';

type NavigateOptions = {
    replace?: boolean;
};

type Props = {
    navigate: (path: string, options?: NavigateOptions) => void;
    oauthClient: OAuthClient<any>;
    successHandler?: () => void;
    errorHandler?: (e: any) => void;
};

export type {Props as UseAuthorizationCodeProps};

export function useAuthorizationCode({
    oauthClient,
    successHandler,
    errorHandler,
    navigate,
}: Props) {
    const [error, setError] = useState<string>();

    useEffectOnce(() => {
        const location = document.location;
        const urlParams = new URLSearchParams(location.search);
        const code = urlParams.get(AuthConstant.ResponseCodeParam);
        const errorName = urlParams.get('error');
        const errorDescription = urlParams.get('error_description');

        if (errorDescription || errorName) {
            setError(errorDescription || errorName!);

            return;
        } else if (!code) {
            setError(`Missing authorization code.`);

            return;
        }

        const state = urlParams.get(AuthConstant.StateParam);

        oauthClient
            .getTokenFromAuthCode(code, location.href.split('?')[0])
            .then(() => {
                if (successHandler) {
                    successHandler();

                    return;
                }
                let redirectPath = '/';
                if (state) {
                    const stateParams = decodeState(state);
                    const r = stateParams[AuthConstant.StateRedirectParam];
                    if (r) {
                        redirectPath = getPathFromRedirectUri(r);
                    }
                }

                if (window.opener) {
                    try {
                        if (window.opener.pendingAuth) {
                            window.opener.document.location.href = redirectPath;
                            window.close();
                        }

                        return;
                    } catch (err) {
                        // eslint-disable-next-line no-console
                        console.error(err);
                    }
                }

                navigate(redirectPath, {replace: true});
            })
            .catch((e: any) => {
                if (errorHandler) {
                    errorHandler(e);

                    return;
                }

                setError(e.message ?? e.toString());
            });
    }, []);

    return {
        error,
    };
}
