import {createS3Client} from "../../s3/s3";
import {getStrict} from "../../configLoader";
import url from "url";
import {S3AmqpConfig} from "./types";

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
