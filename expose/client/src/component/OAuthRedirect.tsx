import {useEffectOnce} from "@alchemy/react-ps";
import {OAuthClient} from "@alchemy/auth";
import qs from "querystring";
import {useHistory, useLocation} from "react-router-dom";

type Props = {
    oauthClient: OAuthClient,
    successUri: string,
    errorUri: string,
    successHandler: () => void,
    errorHandler: (e: any) => void,
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
        oauthClient.getTokenFromAuthCode(
                (qs.parse(location.search.substring(1)) as Record<string, string>).code,
                window.location.href.split('?')[0]
            )
            .then(() => {
                if (successHandler) {
                    return successHandler();
                }

                history.push(successUri || '/');
            }, (e) => {
                if (errorHandler) {
                    return errorHandler(e);
                }

                console.error(e);
                alert(e);
                history.push(errorUri || '/');
            });
    }, []);

    return <></>
}
