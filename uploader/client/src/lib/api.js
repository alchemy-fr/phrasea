import axios from "axios";
import config from '../config';
import {oauthClient} from "../oauth";

export const apiClient = axios.create({
    baseURL: config.getUploadBaseURL(),
})

export function authenticatedRequest(config) {
    return oauthClient.wrapPromiseWithValidToken(async ({access_token, token_type}) => {
        return (await apiClient.request({
            ...config,
            headers: {
                Authorization: `${token_type} ${access_token}`,
                ...(config.headers ?? {}),
            },
        })).data;
    });
}
