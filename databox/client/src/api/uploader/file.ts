import config from '../../config';
import uploaderClient from '../uploader-client';
import {promiseConcurrency} from '../../lib/promises';
import {oauthClient} from '../api-client';
import {RawAxiosRequestHeaders} from 'axios';
import {
    multipartUpload,
    MultipartUploadOptions,
} from '@alchemy/api/src/multiPartUpload.ts';
import {useUploadStore} from '../../store/uploadStore.ts';

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

export function generateUploadId(): string {
    return Math.random().toString(36).substring(2, 15);
}

type UploadedFile = {
    id: string;
    data?: Record<string, any>;
} & FileOrUrl;

export type FileOrUrl =
    | {
          file: File;
          url?: never;
      }
    | {
          file?: never;
          url: string;
      };

export async function UploadFiles(
    files: UploadedFile[],
    formData?: FormData
): Promise<void> {
    const uploadState = useUploadStore.getState();

    files
        .filter(f => Boolean(f.file))
        .forEach(f => {
            uploadState.addUpload({
                id: f.id,
                file: f.file!,
                progress: 0,
            });
        });

    const targetSlug = config.uploaderTargetSlug;
    const assets = (
        await promiseConcurrency(
            files.map(
                f => () =>
                    UploadFile(targetSlug, f, {
                        onProgress: event => {
                            const progress = event.loaded / event.total!;
                            uploadState.uploadProgress({
                                id: f.id,
                                file: f.file!,
                                progress,
                            });
                        },
                    })
            ),
            2
        )
    ).filter(a => a) as string[];

    if (assets.length > 0) {
        await CommitUpload(targetSlug, assets, formData);
    }
}

export async function UploadFile(
    targetSlug: string,
    uploadedFile: UploadedFile,
    multipartUploadOptions: MultipartUploadOptions = {}
): Promise<string | undefined> {
    if (uploadedFile.url) {
        await uploaderClient.post(`/downloads`, {
            targetSlug,
            url: uploadedFile.url,
            data: uploadedFile.data,
        });
        return;
    }

    const multipart = await multipartUpload(
        uploaderClient,
        uploadedFile.file!,
        multipartUploadOptions
    );

    return (
        await uploaderClient.post(`/assets`, {
            targetSlug,
            multipart,
            data: uploadedFile.data,
        })
    ).data.id;
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
