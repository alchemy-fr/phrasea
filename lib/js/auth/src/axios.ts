export type RequestMeta = {
    requestStartedAt?: number;
    responseTime?: number;
};

declare module 'axios' {
    export interface AxiosRequestConfig {
        anonymous?: boolean;
        meta?: RequestMeta;
    }
}
