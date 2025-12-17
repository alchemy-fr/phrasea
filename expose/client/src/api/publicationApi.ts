import {Publication} from '../types.ts';
import {apiClient} from '../init.ts';
import {RawAxiosRequestHeaders} from 'axios';
import {getPasswords} from '../lib/credential';

export async function loadPublication(id: string): Promise<Publication> {
    return (
        await apiClient.get(`/publications/${id}`, {
            headers: getPasswordHeaders(),
        })
    ).data;
}

export function getPasswordHeaders(): RawAxiosRequestHeaders {
    const headers: RawAxiosRequestHeaders = {};

    const passwords = getPasswords();
    if (passwords) {
        headers['X-Passwords'] = passwords;
    }
    return headers;
}
