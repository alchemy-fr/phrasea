import {UploadedFile, uploadMultipartFile} from './multiPartUpload';
import config from '../../config';
import uploaderClient from '../uploader-client';
import {promiseConcurrency} from '../../lib/promises';
import {oauthClient} from '../api-client';
import {RawAxiosRequestHeaders} from 'axios';

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

export async function UploadFiles(
    userId: string,
    files: UploadedFile[],
    formData?: FormData
): Promise<void> {
    const targetSlug = config.uploaderTargetSlug;
    const assets = await promiseConcurrency(
        files.map(f => () => UploadFile(targetSlug, userId, f)),
        2
    );

    await CommitUpload(targetSlug, assets, formData);
}

export async function UploadFile(
    targetSlug: string,
    userId: string,
    uploadedFile: UploadedFile
): Promise<string> {
    return await uploadMultipartFile(targetSlug, userId, uploadedFile);
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
