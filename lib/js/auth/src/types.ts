export interface ValidationError {
    error: string;
    error_description: string;
}

export type AuthEvent = {
    type: string;
    preventDefault?: boolean,
    stopPropagation?: boolean,
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

export type AuthEventHandler<E extends AuthEvent = AuthEvent> = (event: E) => Promise<void>;

export type TokenResponse = {
    access_token: string;
    refresh_token: string;
    token_type: string;
    expires_in: number;
    refresh_expires_in: number;
    device_token?: string;
};

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
