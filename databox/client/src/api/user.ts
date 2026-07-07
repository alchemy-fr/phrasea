import {apiClient} from '../init.ts';
import {Group, User} from '../types';
import {AxiosRequestConfig} from 'axios';
import {UserPreferences} from '../store/userPreferencesStore.ts';
import {QueryAndPaginationParams} from './types.ts';

export async function getUsers(
    options: QueryAndPaginationParams = {},
    config: AxiosRequestConfig = {}
): Promise<User[]> {
    const res = await apiClient.get(`/permissions/users`, {
        params: options.query ? {query: options.query} : undefined,
        ...config,
    });

    return res.data;
}

export async function getGroups(
    options: QueryAndPaginationParams = {}
): Promise<Group[]> {
    const res = await apiClient.get(`/permissions/groups`, {
        params: options.query ? {query: options.query} : undefined,
    });

    return res.data;
}

export async function getUserPreferences(): Promise<UserPreferences> {
    const res = await apiClient.get(`/preferences`);

    return res.data;
}

export type PutPreferenceOptions = {
    reset?: boolean;
    offlineUpdates?: UserPreferences;
};

export async function putUserPreferences(
    name: keyof UserPreferences,
    value: any,
    {reset}: PutPreferenceOptions = {}
): Promise<UserPreferences> {
    const res = await apiClient.put(`/preferences`, {
        name,
        value,
        reset,
    });

    return res.data;
}
