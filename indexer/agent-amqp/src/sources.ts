import {getEnvStrict} from "./env";

export type S3Source = {
    type: 's3';
    endPoint: string;
    port: number
    useSSL: boolean;
    bucketName: string;
    accessKey: string;
    secretKey: string;
}

type Source = (S3Source) & {
    type: "s3",
}

export const sources: Record<string, Source> = {
    s3main: {
        type: 's3',
        port: parseInt(getEnvStrict('S3_STORAGE_PORT')),
        useSSL: false,
        endPoint: getEnvStrict('S3_STORAGE_ENDPOINT'),
        bucketName: getEnvStrict('S3_STORAGE_BUCKET_NAME'),
        accessKey: getEnvStrict('S3_STORAGE_ACCESS_KEY'),
        secretKey: getEnvStrict('S3_STORAGE_SECRET_KEY'),
    }
};

export type SourceName = keyof typeof sources;
