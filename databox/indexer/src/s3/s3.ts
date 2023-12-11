import * as Minio from 'minio';
import type {ClientOptions} from "minio";

export type S3Options = {
    insecure?: boolean;
} & ClientOptions

export function createS3Client({
    insecure,
    ...options
}: S3Options): Minio.Client {
    const client = new Minio.Client(options);
    if (insecure) {
        client.setRequestOptions({rejectUnauthorized: false})
    }

    return client;
}

export async function signUri(client: Minio.Client, bucketName: string, key: string): Promise<string> {
    return await client.presignedGetObject(bucketName, key, 3600);
}
