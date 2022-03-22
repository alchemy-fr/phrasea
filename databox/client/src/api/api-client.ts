import axios, {AxiosRequestConfig} from "axios";

const apiClient = axios.create({
    baseURL: window.config.baseUrl,
});

type RequestConfig = {
    meta?: Record<string, any>;
} & AxiosRequestConfig;

apiClient.interceptors.request.use<RequestConfig>( (x: RequestConfig) => {
    // to avoid overwriting if another interceptor
    // already defined the same object (meta)
    x.meta = x.meta || {}
    x.meta.requestStartedAt = new Date().getTime();

    return x;
});

apiClient.interceptors.response.use<RequestConfig>((x) => {
    console.log(`Execution time for: ${x.config.url} - ${new Date().getTime() - (x.config as RequestConfig).meta!.requestStartedAt} ms`)
    return x;
});

export default apiClient;
