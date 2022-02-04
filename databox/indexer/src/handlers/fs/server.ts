import {AssetServerFactory, notFound} from "../../server";
import {getConfig} from "../../configLoader";
import {FsConfig} from "./types";
import fs from 'fs';

export const fsAssetServerFactory: AssetServerFactory<FsConfig> = function (location, logger) {
    const config = location.options;
    const watchPathPrefix = getConfig('dirPrefix', undefined, config);
    const watchPath = getConfig('dir', '/fs-watch', config);

    return async (path, res, query) => {
        const storagePath = watchPathPrefix ? watchPath+path.substring(watchPathPrefix.length) : path;
        if (!fs.existsSync(storagePath)) {
            return notFound(res, `"${storagePath}" not found`, logger);
        }

        res.sendFile(storagePath);
    }
}
