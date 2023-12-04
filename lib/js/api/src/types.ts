export type RequestMeta = {
    requestStartedAt?: number;
    responseTime?: number;
};

declare module 'axios' {
    export interface AxiosRequestConfig {
        meta?: RequestMeta;
        errorHandled?: boolean;
    }
}
