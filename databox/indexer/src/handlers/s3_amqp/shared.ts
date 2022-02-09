import {createS3Client} from "../../s3/s3";
import {getStrict} from "../../configLoader";
import url from "url";
import {S3AmqpConfig} from "./types";
import {Asset} from "../../indexers";
import {generatePublicUrl} from "../../resourceResolver";

export function createS3ClientFromConfig(config: S3AmqpConfig) {
    const {
        hostname,
        port,
        protocol,
    } = url.parse(getStrict('s3.endpoint', config));

    return createS3Client({
        type: 's3',
        useSSL: protocol === 'https:',
        insecure: true,
        endPoint: hostname,
        port: port ? parseInt(port) : undefined,
        accessKey: getStrict('s3.accessKey', config),
        secretKey: getStrict('s3.secretKey', config),
    })
}

export function createAsset(
    path: string,
    locationName: string,
    bucket: string
): Asset {
    return {
        key: path,
        path,
        publicUrl: generatePublicUrl(path, locationName, {
            bucket,
        }),
        sourcePath: path,
    };
}
