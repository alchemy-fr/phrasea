import * as Minio from 'minio';
import {S3Source, SourceName, sources} from "./sources";

const clients: Record<string, Minio.Client> = {};

export function createS3Client(sourceName: SourceName): Minio.Client {
    if (clients[sourceName]) {
        return clients[sourceName];
    }

    const client = new Minio.Client(sources[sourceName]);
    if (sources[sourceName].insecure) {
        client.setRequestOptions({rejectUnauthorized: false})
    }

    return clients[sourceName] = client;
}

export async function signUri(sourceName: SourceName, key: string): Promise<string> {
    const s = sources[sourceName] as S3Source;

    return await createS3Client(sourceName).presignedGetObject(s.bucketName, key, 3600);
}
