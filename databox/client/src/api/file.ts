import {uploadMultipartFile} from "../lib/upload/multiPartUpload";
import {oauthClient} from "../oauth";
import axios, {AxiosRequestHeaders} from "axios";
import config from "../config";

let uploadId = 0; // TODO use UUID

export const uploadClient = axios.create({
    baseURL: config.get('uploaderApiBaseUrl'),
});

export function makeAuthorizationHeaders(accessToken?: string): AxiosRequestHeaders {
    if (accessToken) {
        return {Authorization: `Bearer ${accessToken}`};
    }

    return {};
}

export type UploadOptions = {
    destinations: string[];
};

export async function UploadFiles(userId: string, files: File[], options: UploadOptions): Promise<void> {
    const targetSlug = config.get('uploaderTargetSlug');
    const assets = await Promise.all(files.map(f => UploadFile(targetSlug, userId, f)));

    await CommitUpload(targetSlug, assets, options);
}

export async function UploadFile(targetSlug: string, userId: string, file: File): Promise<string> {
    return await uploadMultipartFile(targetSlug, userId, oauthClient.getAccessToken(), {
        file,
        id: (uploadId++).toString()
    }, (e) => {
        console.log('e', e);
    });
}

export async function CommitUpload(targetSlug: string, files: string[], options: UploadOptions): Promise<void> {
    await uploadClient.post(`/commit`, {
        targetSlug,
        files,
        options,
    }, {
        headers: makeAuthorizationHeaders(oauthClient.getAccessToken()),
    });
}
