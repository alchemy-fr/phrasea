import apiClient from './api-client';
import {Group, User} from '../types';
import {UserPreferences} from '../components/User/Preferences/UserPreferencesContext';
import {AxiosRequestConfig} from 'axios';

type QueryOptions = {
    query?: string;
};

export async function getUsers(
    options: QueryOptions = {},
    config: AxiosRequestConfig = {}
): Promise<User[]> {
    const res = await apiClient.get(`/permissions/users`, {
        params: options.query ? {query: options.query} : undefined,
        ...config,
    });

    return res.data;
}

export async function getGroups(options: QueryOptions = {}): Promise<Group[]> {
    const res = await apiClient.get(`/permissions/groups`, {
        params: options.query ? {query: options.query} : undefined,
    });

    return res.data;
}

export async function getUserPreferences(): Promise<UserPreferences> {
    const res = await apiClient.get(`/preferences`);

    return res.data;
}

export async function putUserPreferences(
    name: string,
    value: any
): Promise<UserPreferences> {
    const res = await apiClient.put(`/preferences`, {
        name,
        value,
    });

    return res.data;
}
