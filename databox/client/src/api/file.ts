import {uploadMultipartFile} from "../lib/upload/multiPartUpload";
import {oauthClient} from "../oauth";
import {AxiosRequestHeaders, HeadersDefaults, RawAxiosRequestHeaders} from "axios";
import config from "../config";
import uploaderClient from "./uploader-client";

let uploadId = 0; // TODO use UUID

interface MyHeaders extends RawAxiosRequestHeaders {
    Authorization?: string;
}

export function makeAuthorizationHeaders(accessToken?: string): MyHeaders {
    if (accessToken) {
        return {Authorization: `Bearer ${accessToken}`};
    }

    return {};
}

export type UploadOptions = {
    title?: string | undefined;
    destinations: string[];
};

type FormData = Record<string, any> | undefined;

export async function UploadFiles(userId: string, files: File[], options: UploadOptions, formData?: FormData): Promise<void> {
    const targetSlug = config.get('uploaderTargetSlug');
    const assets = await Promise.all(files.map(f => UploadFile(targetSlug, userId, f)));

    await CommitUpload(targetSlug, assets, options, formData);
}

export async function UploadFile(targetSlug: string, userId: string, file: File): Promise<string> {
    return await uploadMultipartFile(targetSlug, userId, oauthClient.getAccessToken()!, {
        file,
        id: (uploadId++).toString()
    }, (e) => {
        console.log('e', e);
    });
}

export async function CommitUpload(targetSlug: string, files: string[], options: UploadOptions, formData?: FormData): Promise<void> {
    await uploaderClient.post(`/commit`, {
        targetSlug,
        files,
        options,
        formData,
    }, {
        headers: makeAuthorizationHeaders(oauthClient.getAccessToken()!),
    });
}
