import {uploadMultipartFile} from "../lib/upload/multiPartUpload";
import {oauthClient} from "../oauth";
import axios from "axios";
import config from "../config";

let uploadId = 0; // TODO use UUID

export const uploadClient = axios.create({
    baseURL: config.get('uploaderApiBaseUrl'),
});

export function makeAuthorizationHeaders(accessToken?: string): object {
    if (accessToken) {
        return { Authorization: `Bearer ${accessToken}` };
    }

    return {};
}

export async function UploadFiles(userId: string, files: File[]): Promise<void> {
    const assets = await Promise.all(files.map(f => UploadFile(userId, f)));

    await CommitUpload(assets);
}

export async function UploadFile(userId: string, file: File): Promise<string> {
    return await uploadMultipartFile(userId, oauthClient.getAccessToken(), {
        file,
        id: (uploadId++).toString()
    }, (e) => {
        console.log('e', e);
    });
}

export async function CommitUpload(files: string[]): Promise<void> {
    await uploadClient.post(`/commit`, {
        files,
    }, {
        headers: makeAuthorizationHeaders(oauthClient.getAccessToken()),
    });
}
