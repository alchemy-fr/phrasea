import config from '../../config';
import uploaderClient from '../uploader-client';
import {promiseConcurrency} from '../../lib/promises';
import {oauthClient} from '../api-client';
import {RawAxiosRequestHeaders} from 'axios';
import {multipartUpload} from "../../../../../lib/js/api/src/multiPartUpload.ts";

interface MyHeaders extends RawAxiosRequestHeaders {
    Authorization?: string;
}

export async function makeAuthorizationHeaders(): Promise<MyHeaders> {
    if (oauthClient.isAuthenticated()) {
        if (!oauthClient.isAccessTokenValid()) {
            await oauthClient.getTokenFromRefreshToken();
        }

        return {Authorization: `Bearer ${oauthClient.getAccessToken()!}`};
    }

    return {};
}

type FormData = Record<string, any> | undefined;

type UploadedFile = {
    data?: Record<string, any>;
    file: File;
};
export async function UploadFiles(
    files: UploadedFile[],
    formData?: FormData
): Promise<void> {
    const targetSlug = config.uploaderTargetSlug;
    const assets = await promiseConcurrency(
        files.map(f => () => UploadFile(targetSlug, f)),
        2
    );

    await CommitUpload(targetSlug, assets, formData);
}

export async function UploadFile(
    targetSlug: string,
    uploadedFile: UploadedFile
): Promise<string> {
    const multipart = await multipartUpload(uploaderClient, uploadedFile.file, {
    });

    return (await uploaderClient.post(`/assets`, {
        targetSlug,
        multipart,
        data: uploadedFile.data,
    })).data.id;
}

export async function CommitUpload(
    targetSlug: string,
    files: string[],
    formData?: FormData
): Promise<void> {
    await uploaderClient.post(
        `/commit`,
        {
            targetSlug,
            files,
            formData,
        },
        {
            headers: await makeAuthorizationHeaders(),
        }
    );
}
