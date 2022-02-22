import {AssetServerFactory, notFound} from "../../server";
import {IndexLocation} from "../../types/config";
import {S3AmqpConfig} from "./types";
import {signUri} from "../../s3/s3";
import {createS3ClientFromConfig} from "./shared";

export const s3AmqpAssetServerFactory: AssetServerFactory<S3AmqpConfig> = function (location: IndexLocation<S3AmqpConfig>, logger) {
    const s3Client = createS3ClientFromConfig(location.options);
    const allowedBuckets = location.options.s3.bucketNames.split(',');
    return async (path, res, query) => {
        const bucketName = query.bucket;

        if (!allowedBuckets.includes(bucketName)) {
            return notFound(res, `Invalid bucket`, logger);
        }

        res.redirect(307, await signUri(s3Client, bucketName, path));
    }
}
