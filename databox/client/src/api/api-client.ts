import axios, {AxiosRequestConfig, AxiosResponse} from "axios";

const apiClient = axios.create({
    baseURL: window.config.baseUrl,
});

type RequestMeta = {
    requestStartedAt?: number;
    responseTime?: number;
};

export type RequestConfig = {meta?: RequestMeta} & AxiosRequestConfig<RequestMeta>;

apiClient.interceptors.request.use<RequestConfig>( (config: RequestConfig) => {
    // to avoid overwriting if another interceptor
    // already defined the same object (meta)
    config.meta = config.meta || {};
    config.meta!.requestStartedAt = new Date().getTime();

    return config;
});

apiClient.interceptors.response.use<AxiosResponse<any, {
    meta?: Record<string, any>;
    responseTime?: number;
}>>((r) => {
    const meta = (r.config as RequestConfig).meta!;

    const responseTime = new Date().getTime() - meta.requestStartedAt!;
    meta.responseTime = responseTime;
    console.log(`Execution time for: ${r.config.url} - ${responseTime} ms`)

    return r;
});

export default apiClient;
