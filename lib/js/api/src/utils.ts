import axios, {AxiosError} from 'axios';
import {ApiConstant, SimpleAxiosError} from './types';

export function getApiResponseError(e: any): string | undefined {
    const error = getAxiosError(e);

    if (error) {
        if (error.code === 422 && error.data?.violations) {
            return error.data.violations
                .map((v: {message: string}) => v.message)
                .join('\n');
        }

        return error.message;
    }
}

export function getBestErrorProp(data: any): string | undefined {
    if (!data) {
        return;
    }

    if (data[ApiConstant.HydraTitle] && data[ApiConstant.HydraDescription]) {
        return `${data[ApiConstant.HydraTitle]}: ${data[ApiConstant.HydraDescription]}`;
    }

    return (
        data['error_message'] ??
        data['detail'] ??
        data['message'] ??
        data[ApiConstant.HydraDescription] ??
        data[ApiConstant.HydraTitle] ??
        data['title']
    );
}

export function isErrorOfCode(e: any, codes: number[]): e is AxiosError {
    return axios.isAxiosError(e) && codes.includes(e.response?.status ?? 0);
}

export function getAxiosError<Data = any>(
    error: any
): SimpleAxiosError<Data> | undefined {
    if (axios.isAxiosError(error)) {
        const data = (error as AxiosError).response?.data as Data | undefined;

        return {
            data,
            error,
            code: error.response?.status ?? 0,
            message: getBestErrorProp(data) ?? ApiConstant.UnknownError,
        };
    }
}
