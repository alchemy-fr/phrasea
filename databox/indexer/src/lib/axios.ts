import axios, {AxiosError, AxiosInstance} from "axios";
import * as https from "https";
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
                                     retries = 10,
                                     ...rest
                                 }: Options): AxiosInstance {
    if (false === verifySSL) {
        process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
    }

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
            if (!config) {
                return false;
            }
            logger.warn(`Request "${config.method?.toUpperCase()} ${config.url}" failed, retrying...`);

            if (error.response) {
                if ([500, 400, 422, 404, 403, 401].includes(error.response.status)) {
                    return false;
                }

                logger.debug(`Request "${config.method?.toUpperCase()} ${config.url}" response ${error.response.status}: ${JSON.stringify(error.response.data)}`);
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
                let filtered: any = error.response.data;

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
