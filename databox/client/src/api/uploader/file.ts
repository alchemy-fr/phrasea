import {UploadedFile, uploadMultipartFile} from "./multiPartUpload";
import {oauthClient} from "../../oauth";
import {RawAxiosRequestHeaders} from "axios";
import config from "../../config";
import uploaderClient from "../uploader-client";

interface MyHeaders extends RawAxiosRequestHeaders {
    Authorization?: string;
}

export function makeAuthorizationHeaders(accessToken?: string): MyHeaders {
    if (accessToken) {
        return {Authorization: `Bearer ${accessToken}`};
    }

    return {};
}

type FormData = Record<string, any> | undefined;

export async function UploadFiles(userId: string, files: UploadedFile[], formData?: FormData): Promise<void> {
    const targetSlug = config.get('uploaderTargetSlug');
    const assets = await Promise.all(files.map(f => UploadFile(targetSlug, userId, f)));

    await CommitUpload(targetSlug, assets, formData);
}

export async function UploadFile(targetSlug: string, userId: string, uploadedFile: UploadedFile): Promise<string> {
    return await uploadMultipartFile(targetSlug, userId, oauthClient.getAccessToken()!, uploadedFile);
}

export async function CommitUpload(targetSlug: string, files: string[], formData?: FormData): Promise<void> {
    await uploaderClient.post(`/commit`, {
        targetSlug,
        files,
        formData,
    }, {
        headers: makeAuthorizationHeaders(oauthClient.getAccessToken()!),
    });
}
