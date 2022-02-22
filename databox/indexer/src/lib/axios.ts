import axios, {AxiosInstance} from "axios";
import https from "https";

type Options = {
    baseURL: string;
    verifySSL?: boolean;
} & Record<string, any>;

export function createHttpClient({
                                     verifySSL = true,
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

    client.interceptors.response.use(
        response => response,
        error => {
            console.error(error.message);
            if (error.response) {
                const filtered = {
                    ...error.response.data,
                    trace: ['filtered...'],
                }

                console.error(filtered);
            }

            return Promise.reject(error);
        });

    return client;
}