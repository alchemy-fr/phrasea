import {AssetServerFactory, notFound} from "../../server";
import {FsConfig} from "./types";
import fs from 'fs';
import {getDirConfig} from "./shared";

export const fsAssetServerFactory: AssetServerFactory<FsConfig> = function (location, logger) {
    const {
        watchDir,
        dirPrefix,
    } = getDirConfig(location.options);

    return async (path, res, query) => {
        const storagePath = dirPrefix ? watchDir + path.substring(dirPrefix.length) : path;
        if (!fs.existsSync(storagePath)) {
            return notFound(res, `"${storagePath}" not found`, logger);
        }

        res.sendFile(storagePath);
    }
}
