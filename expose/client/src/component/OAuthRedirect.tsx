import React from 'react';
import {OAuthClient, useEffectOnce} from "react-ps";
import qs from "querystring";
import {useHistory, useLocation} from "react-router-dom";
import * as H from "history";

type Props = {
    oauthClient: OAuthClient,
    successUri: string,
    errorUri: string,
    successHandler: (history: H.History) => void,
    errorHandler: (e: any, history: H.History) => void,
};

export default function OAuthRedirect({
    oauthClient,
    successUri,
    errorUri,
    successHandler,
    errorHandler,
}: Props) {
    const history = useHistory();
    const location = useLocation();

    useEffectOnce(() => {
        oauthClient.getAccessTokenFromAuthCode(
                (qs.parse(location.search.substring(1)) as Record<string, string>).code,
                window.location.href.split('?')[0]
            )
            .then(() => {
                if (successHandler) {
                    return successHandler(history);
                }

                history.push(successUri || '/');
            }, (e) => {
                if (errorHandler) {
                    return errorHandler(e, history);
                }

                console.error(e);
                alert(e);
                history.push(errorUri || '/');
            });
    }, []);

    return <></>
}
