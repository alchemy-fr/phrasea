import apiClient from './api-client';
import {AlternateUrl, ApiFile} from '../types';
import {AttributeBatchAction} from './types.ts';
import {AxiosRequestConfig} from 'axios';

export async function getFile(id: string): Promise<ApiFile> {
    return (await apiClient.get(`/files/${id}`)).data;
}

export async function getFileMetadata(id: string): Promise<ApiFile> {
    return (await apiClient.get(`/files/${id}/metadata`)).data;
}

export function fileToDataUri(file: File): Promise<string> {
    return new Promise(resolve => {
        const reader = new FileReader();
        reader.onload = event => {
            resolve(event.target!.result as string);
        };
        reader.readAsDataURL(file);
    });
}

export type SourceFileInput = {
    url?: string;
    originalName?: string;
    type?: string;
    isPrivate?: boolean;
    importFile?: boolean;
    alternateUrls?: AlternateUrl[];
};

export type FileInputFromFile = {
    file: File;
    url?: never;
};

export type FileInputFromUrl = {
    file?: never;
    url: string;
    importFile?: boolean;
};

export type FileOrUrl = FileInputFromFile | FileInputFromUrl;

export type UploadedFile = {
    id: string;
    data?: Record<string, any>;
} & FileOrUrl;

export type CreateAssetsOptions = {
    quiet?: boolean;
    isStory?: boolean;
    story?:
        | {
              title?: string;
              tags?: string[];
              attributes?: AttributeBatchAction[] | undefined;
          }
        | undefined;
    config?: AxiosRequestConfig;
};
