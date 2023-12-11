import apiClient from './api-client';
import {Group, User} from '../types';
import {UserPreferences} from '../components/User/Preferences/UserPreferencesContext';

export async function getUsers(): Promise<User[]> {
    const res = await apiClient.get(`/permissions/users`);

    return res.data;
}

export async function getGroups(): Promise<Group[]> {
    const res = await apiClient.get(`/permissions/groups`);

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
