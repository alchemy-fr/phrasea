import {oauthClient} from "./oauth";

export function authenticate() {
    return oauthClient.authenticate();
}
