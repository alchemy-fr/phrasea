import {AxiosRequestConfig as BaseAxiosRequestConfig} from 'axios';
import {type IAxiosRetryConfigExtended} from 'axios-retry';

declare module 'axios' {
    export interface AxiosRequestConfig extends BaseAxiosRequestConfig {
        'anonymous'?: boolean;
        'axios-retry'?: IAxiosRetryConfigExtended;
    }
}
