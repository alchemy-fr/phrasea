import {getPasswords} from '../lib/credential';
import {Asset, Publication} from '../types';
import {RawAxiosRequestHeaders} from 'axios';
import apiClient from '../lib/api-client';

export async function loadPublication(id: string): Promise<Publication> {
    return (
        await apiClient.get(`/publications/${id}`, {
            headers: getPasswordHeaders(),
        })
    ).data;
}

export async function loadAsset(id: string): Promise<Asset> {
    return (
        await apiClient.get(`/assets/${id}`, {
            headers: getPasswordHeaders(),
        })
    ).data;
}

function getPasswordHeaders(): RawAxiosRequestHeaders {
    const headers: RawAxiosRequestHeaders = {};

    const passwords = getPasswords();
    if (passwords) {
        headers['X-Passwords'] = passwords;
    }
    return headers;
}
