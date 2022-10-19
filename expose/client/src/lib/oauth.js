import config from "./config";
import {OAuthClient} from "react-ps";

const {clientId, clientSecret} = config.getClientCredential();

export const oauthClient = new OAuthClient({
    clientId,
    clientSecret,
    baseUrl: config.getAuthBaseUrl(),
});

const authRedirectKey = 'auth_redirect';
export function setAuthRedirect(uri) {
    sessionStorage.setItem(authRedirectKey, uri);
}
export function getAuthRedirect() {
    return sessionStorage.getItem(authRedirectKey);
}

export function unsetAuthRedirect() {
    return sessionStorage.removeItem(authRedirectKey);
}
