import {AssetServerFactory} from "../../server";
import {IndexLocation} from "../../types/config";
import {S3AmqpConfig} from "./types";
import {signUri} from "../../s3/s3";
import {createS3ClientFromConfig} from "./shared";

export const s3AmqpAssetServerFactory: AssetServerFactory<S3AmqpConfig> = function (location: IndexLocation<S3AmqpConfig>) {
    const s3Client = createS3ClientFromConfig(location.options);

    return async (path, res, query) => {
        res.redirect(307, await signUri(s3Client, query.bucket, path));
    }
}
