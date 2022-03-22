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
        return { Authorization: `Bearer ${accessToken}` };
    }

    return {};
}

export type UploadOptions = {
    destinations: string[];
};

export async function UploadFiles(userId: string, files: File[], options: UploadOptions): Promise<void> {
    const assets = await Promise.all(files.map(f => UploadFile(userId, f)));

    await CommitUpload(assets, options);
}

export async function UploadFile(userId: string, file: File): Promise<string> {
    return await uploadMultipartFile(userId, oauthClient.getAccessToken(), {
        file,
        id: (uploadId++).toString()
    }, (e) => {
        console.log('e', e);
    });
}

export async function CommitUpload(files: string[], options: UploadOptions): Promise<void> {
    await uploadClient.post(`/commit`, {
        files,
        options,
    }, {
        headers: makeAuthorizationHeaders(oauthClient.getAccessToken()),
    });
}
