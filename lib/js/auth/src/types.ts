import {TokenResponse} from "./client/OAuthClient";

export type AuthTokens = {
    accessToken: string;
    expiresIn: number;
    expiresAt: number;
    refreshToken?: string;
    refreshExpiresIn?: number;
    refreshExpiresAt?: number;
    deviceToken?: string;
    tokenType: string;
}

export type TokenResponseWithTokens = {
    tokens: AuthTokens;
} & TokenResponse;

export type UserInfoResponse = {};

export type KeycloakUserInfoResponse = {
    preferred_username: string;
    groups: string[];
    roles: string[];
    sub: string;
} & UserInfoResponse;

export type AuthUser = {
    id: string;
    username: string;
    roles: string[];
    groups: string[];
};

export type UserNormalizer<U extends AuthUser, UIR extends UserInfoResponse> = (payload: UIR) => U;

declare module 'axios' {
    export interface AxiosRequestConfig {
        anonymous?: boolean;
    }
}
