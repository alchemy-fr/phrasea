import apiClient from "./api-client";
import {Group, User} from "../types";
import config from "../config";

export async function getUsers(): Promise<User[]> {
    const res = await apiClient.get(`${config.get('authBaseUrl')}/users`);

    return res.data;
}

export async function getGroups(): Promise<Group[]> {
    const res = await apiClient.get(`${config.get('authBaseUrl')}/groups`);

    return res.data;
}
