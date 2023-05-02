import {getPasswords} from "../lib/credential";
import {oauthClient} from "../lib/oauth";
import apiClient from "../lib/apiClient";
import config from "../lib/config";
import {Asset, Publication} from "../types";

export async function loadPublication(id: string): Promise<Publication> {
    const options: Record<string, any> = {};

    const passwords = getPasswords();
    if (passwords) {
        options.headers = {'X-Passwords': passwords};
    }

    const accessToken = oauthClient.getAccessToken();
    if (accessToken) {
        options.headers = {'Authorization': `Bearer ${accessToken}`};
    }

    return await apiClient.get(`${config.getApiBaseUrl()}/publications/${id}`, {}, options);
}

export async function loadAsset(id: string): Promise<Asset> {
    const options: Record<string, any> = {};

    const passwords = getPasswords();
    if (passwords) {
        options.headers = {'X-Passwords': passwords};
    }

    const accessToken = oauthClient.getAccessToken();
    if (accessToken) {
        options.headers = {'Authorization': `Bearer ${accessToken}`};
    }

    return await apiClient.get(`${config.getApiBaseUrl()}/assets/${id}`, {}, options);
}
