import {AxiosRequestConfig as BaseAxiosRequestConfig} from 'axios';

declare module 'axios' {
    export interface AxiosRequestConfig extends BaseAxiosRequestConfig {
        anonymous?: boolean;
    }
}
