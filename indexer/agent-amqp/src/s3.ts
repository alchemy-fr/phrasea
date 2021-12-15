import Minio from 'minio';
import {S3Source, SourceName, sources} from "./sources";

const clients: Record<string, Minio.Client> = {};

function createS3Client(sourceName: SourceName) {
    return clients[sourceName] = new Minio.Client(sources[sourceName]);
}

export async function signUri(sourceName: SourceName, key: string): Promise<string>
{
    const s = sources[sourceName] as S3Source;

    return await createS3Client(sourceName).presignedGetObject(s.bucketName, key, 3600);
}
