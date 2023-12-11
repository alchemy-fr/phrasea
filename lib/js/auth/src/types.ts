
export type Jwt = Record<string, any>;

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

export type AuthUser = {
    id: string;
    username: string;
    roles: string[];
    groups: string[];
};

declare module 'axios' {
    export interface AxiosRequestConfig {
        anonymous?: boolean;
    }
}
