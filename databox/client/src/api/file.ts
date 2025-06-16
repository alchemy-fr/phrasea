import apiClient from './api-client';
import {File} from '../types';

export async function getFile(id: string): Promise<File> {
    return (await apiClient.get(`/files/${id}`)).data;
}

export async function getFileMetadata(id: string): Promise<File> {
    return (await apiClient.get(`/files/${id}/metadata`)).data;
}
