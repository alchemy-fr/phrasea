import axios, {AxiosError, AxiosInstance, AxiosRequestConfig, AxiosResponse} from "axios";

type HttpClient = {
    errorListeners: ErrorListener[];
    addErrorListener: (listener: ErrorListener) => void;
    removeErrorListener: (listener: ErrorListener) => void;
    setApiLocale: (locale: string) => void;
} & AxiosInstance;

type RequestMeta = {
    requestStartedAt?: number;
    responseTime?: number;
};

type ErrorListener = (error: AxiosError) => void;

export type RequestConfig = { meta?: RequestMeta } & AxiosRequestConfig<RequestMeta>;

export function createHttpClient(baseURL: string): HttpClient
{
    const client = axios.create({
        baseURL,
    }) as HttpClient;

    client.interceptors.request.use<RequestConfig>((config: RequestConfig) => {
        // to avoid overwriting if another interceptor
        // already defined the same object (meta)
        config.meta = config.meta || {};
        config.meta!.requestStartedAt = new Date().getTime();

        return config;
    });

    client.errorListeners = [];

    client.interceptors.response.use<AxiosResponse<any, {
        meta?: Record<string, any>;
        responseTime?: number;
    }>>((r) => {
        const meta = (r.config as RequestConfig).meta!;

        const responseTime = new Date().getTime() - meta.requestStartedAt!;
        meta.responseTime = responseTime;
        console.log(`Execution time for: ${r.config.method?.toUpperCase()} ${r.config.url} - ${responseTime} ms`)

        return r;
    }, (error: AxiosError) => {
        client.errorListeners.forEach(l => l(error));

        return Promise.reject(error);
    });

    client.addErrorListener = function (listener: ErrorListener): void {
        this.errorListeners.push(listener);
    }

    client.removeErrorListener = function (listener: ErrorListener): void {
        const i = this.errorListeners.findIndex(l => l === listener);
        this.errorListeners.splice(i, 1);
    }

    client.setApiLocale = function (locale: string): void {
        this.defaults.headers.common['Accept-Language'] = locale.replace(/_/g, '-');
    }

    return client;
}
