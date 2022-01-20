import {getEnvStrict} from "./env";
import url from 'url';

export type S3Source = {
    type: 's3';
    endPoint: string;
    port: number
    useSSL: boolean;
    insecure?: boolean;
    bucketName: string;
    accessKey: string;
    secretKey: string;
}

type Source = (S3Source) & {
    type: string,
}

const {
    hostname,
    port,
    protocol,
} = url.parse(getEnvStrict('S3_STORAGE_ENDPOINT'));

export const sources: Record<string, Source> = {
    s3main: {
        type: 's3',
        useSSL: protocol === 'https:',
        insecure: true,
        endPoint: hostname,
        port: port ? parseInt(port) : undefined,
        bucketName: getEnvStrict('S3_STORAGE_BUCKET_NAME'),
        accessKey: getEnvStrict('S3_STORAGE_ACCESS_KEY'),
        secretKey: getEnvStrict('S3_STORAGE_SECRET_KEY'),
    }
};

export type SourceName = keyof typeof sources;
