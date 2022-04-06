import axios, {AxiosError, AxiosInstance} from "axios";
import https from "https";
import axiosRetry from "axios-retry";
import {createLogger} from "./logger";

type Options = {
    baseURL: string;
    verifySSL?: boolean;
    retries?: number;
} & Record<string, any>;

const logger = createLogger('http');

export function createHttpClient({
                                     verifySSL = true,
                                     retries = 20,
                                     ...rest
                                 }: Options): AxiosInstance {
    const client = axios.create({
        timeout: 30000,
        headers: {
            'Accept': 'application/json',
        },
        httpsAgent: new https.Agent({
            rejectUnauthorized: verifySSL
        }),
        ...rest,
    });

    axiosRetry(client, {
        retries,
        shouldResetTimeout: true,
        retryCondition: (error) => {
            const {config} = error;
            logger.warn(`Request "${config.method.toUpperCase()} ${config.url}" failed, retrying...`);

            if (error.response) {
                if ([500, 400, 404, 403, 401].includes(error.response.status)) {
                    return false;
                }
            }

            return true;
        },
        retryDelay: (retryCount) => {
            return retryCount * 1000;
        }
    });

    client.interceptors.response.use(
        response => response,
        (error: AxiosError) => {
            logger.error(error.message);
            if (error.response) {
                let filtered = error.response.data;

                if (typeof filtered === 'object' && filtered.trace) {
                    filtered = {
                        ...filtered,
                        trace: ['filtered...'],
                    }
                }

                logger.error('Error response: '+JSON.stringify(filtered, undefined, 2));
            }

            return Promise.reject(error);
        });

    return client;
}
