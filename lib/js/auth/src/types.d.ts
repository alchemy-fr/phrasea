
export type Jwt = Record<string, any>;

export type AuthTokens = {
    accessToken: string;
    expiresIn: number;
    refreshToken?: string;
    refreshExpiresIn?: number;
    deviceToken?: string;
}
