import apiClient from './api-client';
import {ApiFile} from '../types';
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

export type FileOrUrl =
    | {
          file: File;
          url?: never;
      }
    | {
          file?: never;
          url: string;
      };

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
