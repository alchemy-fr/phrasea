
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

declare module 'axios' {
    export interface AxiosRequestConfig {
        anonymous?: boolean;
    }
}
