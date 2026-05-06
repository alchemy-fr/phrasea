import axios, {AxiosError} from 'axios';
import type {ErrorListener, HttpClient} from './types';
import axiosRetry from 'axios-retry';

type CreateClientOptions = {
    retries?: number;
};

export function createHttpClient(
    baseURL: string,
    {retries = 3}: CreateClientOptions = {}
): HttpClient {
    const client = axios.create({
        baseURL,
    }) as HttpClient;

    client.interceptors.request.use(config => {
        // to avoid overwriting if another interceptor
        // already defined the same object (meta)
        config.meta = config.meta || {};
        config.meta!.requestStartedAt = new Date().getTime();

        return config;
    });

    client.errorListeners = [];

    client.interceptors.response.use(
        r => {
            const meta = r.config.meta!;

            const responseTime = new Date().getTime() - meta.requestStartedAt!;
            meta.responseTime = responseTime;

            // eslint-disable-next-line no-console
            console.debug(
                `Execution time for: ${r.config.method?.toUpperCase()} ${r.config.url} - ${responseTime} ms`
            );

            return r;
        },
        (error: AxiosError) => {
            console.log('errorL', error, client.errorListeners);
            client.errorListeners.forEach(l => l(error));

            return Promise.reject(error);
        }
    );

    client.addErrorListener = function (listener: ErrorListener): void {
        this.errorListeners.push(listener);
    };

    client.removeErrorListener = function (listener: ErrorListener): void {
        const i = this.errorListeners.findIndex(l => l === listener);
        this.errorListeners.splice(i, 1);
    };

    client.setApiLocale = function (locale: string): void {
        const l = locale.replace(/_/g, '-');
        const languages = [
            l,
            ...window.navigator.languages.filter(l => l !== locale),
        ];
        this.defaults.headers.common['Accept-Language'] = languages.join(', ');
    };

    axiosRetry(client, {
        retries,
        shouldResetTimeout: true,
        retryCondition: error => {
            const {config} = error;
            if (!config) {
                return false;
            }

            if (error.response) {
                if (
                    [500, 400, 422, 404, 403, 401].includes(
                        error.response.status
                    )
                ) {
                    return false;
                }
            }

            // eslint-disable-next-line no-console
            console.warn(
                `Request "${config.method?.toUpperCase()} ${
                    config.url
                }" failed, retrying...`
            );

            if (error.response) {
                // eslint-disable-next-line no-console
                console.debug(
                    `Request "${config.method?.toUpperCase()} ${
                        config.url
                    }" response ${error.response.status}: ${JSON.stringify(
                        error.response.data
                    )}`
                );
            }

            return true;
        },
        retryDelay: retryCount => {
            return retryCount * 1000;
        },
    });

    return client;
}
