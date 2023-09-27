import config from "../config";
import {useLocation} from "react-router-dom";
import {oauthClient} from "../api/api-client";

export function useKeycloakUrls() {
    const location = useLocation();

    return {
        getLoginUrl: () => oauthClient.createAuthorizeUrl({
            connectTo: config.autoConnectIdP || undefined,
            state: btoa(JSON.stringify({r: location})),
        }),
        getAccountUrl: () => `${oauthClient.getAccountUrl()}`,
    }
}
