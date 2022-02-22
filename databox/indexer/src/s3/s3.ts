import * as Minio from 'minio';

export type S3Options = {
    type: 's3';
    endPoint: string;
    port: number
    useSSL: boolean;
    insecure?: boolean;
    bucketName?: string;
    accessKey: string;
    secretKey: string;
}

export function createS3Client(options: S3Options): Minio.Client {
    const client = new Minio.Client(options);
    if (options.insecure) {
        client.setRequestOptions({rejectUnauthorized: false})
    }

    return client;
}

export async function signUri(client: Minio.Client, bucketName: string, key: string): Promise<string> {
    return await client.presignedGetObject(bucketName, key, 3600);
}
