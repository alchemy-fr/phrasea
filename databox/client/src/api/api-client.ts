import axios, {AxiosError, AxiosRequestConfig, AxiosResponse} from "axios";

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

type ErrorListener = (error: AxiosError) => void;
const errorListeners: ErrorListener[] = [];

apiClient.interceptors.response.use<AxiosResponse<any, {
    meta?: Record<string, any>;
    responseTime?: number;
}>>((r) => {
    const meta = (r.config as RequestConfig).meta!;

    const responseTime = new Date().getTime() - meta.requestStartedAt!;
    meta.responseTime = responseTime;
    console.log(`Execution time for: ${r.config.method?.toUpperCase()} ${r.config.url} - ${responseTime} ms`)

    return r;
}, (error: AxiosError) => {
    errorListeners.forEach(l => l(error));

    return Promise.reject(error);
});

export function addErrorListener(listener: ErrorListener): void {
    errorListeners.push(listener);
}

export function removeErrorListener(listener: ErrorListener): void {
    const i = errorListeners.findIndex(l => l === listener);
    errorListeners.splice(i, 1);
}

export function setApiLocale(locale: string): void {
    apiClient.defaults.headers.common['Accept-Language'] = locale.replace(/_/g, '-');
}


export default apiClient;
