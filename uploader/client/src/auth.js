import config from "./config";
import {oauthClient} from "./oauth";

export function authenticate() {
    return oauthClient.authenticate(config.getUploadBaseURL() + '/me');
}
