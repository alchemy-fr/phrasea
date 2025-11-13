import type {CookieStorageOptions} from '@alchemy/storage';
import {IStorage} from '@alchemy/storage';
import {HttpClient} from '@alchemy/api';

export interface ValidationError {
    error: string;
    error_description: string;
}

export type AuthEvent = {
    type: string;
    preventDefault?: boolean;
    stopPropagation?: boolean;
};

export type LoginEvent = {
    tokens: AuthTokens;
} & AuthEvent;

export type LogoutOptions = {
    quiet?: boolean;
    redirectPath?: string | undefined;
    noEvent?: boolean;
};

export type LogoutEvent = LogoutOptions & AuthEvent;

export type SessionExpiredEvent = AuthEvent;

export type RefreshTokenEvent = {
    tokens: AuthTokens;
} & AuthEvent;

export type AuthEventHandler<E extends AuthEvent = AuthEvent> = (
    event: E
) => Promise<void>;

export type TokenResponse = {
    id_token?: string;
    access_token: string;
    refresh_token?: string;
    token_type: string;
    expires_in: number;
    refresh_expires_in?: number;
    device_token?: string;
    device_token_expires_in?: number;
};

export type AuthTokens = {
    accessToken: string;
    idToken?: string;
    expiresIn: number;
    expiresAt: number;
    refreshToken?: string;
    refreshExpiresIn?: number;
    refreshExpiresAt?: number;
    deviceToken?: string;
    deviceTokenExpiresIn?: number;
    deviceTokenExpiresAt?: number;
    tokenType: string;
};

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

export type KeycloakUser = {} & AuthUser;

export type UserNormalizer<U extends AuthUser, UIR extends UserInfoResponse> = (
    payload: UIR
) => U;

declare module 'axios' {
    export interface AxiosRequestConfig {
        anonymous?: boolean;
        retryAfterNewToken?: boolean;
    }
}

export type PendingAuthWindow = {
    pendingAuth?: boolean;
} & Window;

export enum GrantTypeRefreshMethod {
    refreshToken = 'getTokenFromRefreshToken',
    clientCredentials = 'getTokenFromClientCredentials',
}

export type OAuthClientOptions = {
    storage?: IStorage;
    clientId: string;
    clientSecret?: string;
    baseUrl: string;
    tokenStorageKey?: string;
    httpClient?: HttpClient;
    scope?: string | undefined;
    cookiesOptions?: CookieStorageOptions['cookiesOptions'];
    autoRefreshToken?: boolean;
};

export type KeycloakClientOptions = {
    realm: string;
} & OAuthClientOptions;

export enum OAuthEvent {
    login = 'login',
    logout = 'logout',
    refreshToken = 'refreshToken',
    sessionExpired = 'sessionExpired',
}
