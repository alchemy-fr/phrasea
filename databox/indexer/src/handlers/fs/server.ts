import {AssetServerFactory, notFound} from "../../server";
import {getConfig} from "../../configLoader";
import {FsConfig} from "./types";
import fs from 'fs';
import {createLogger} from "../../lib/logger";

export const fsAssetServerFactory: AssetServerFactory<FsConfig> = function (location) {
    const config = location.options;
    const watchPathPrefix = getConfig('dirPrefix', undefined, config);
    const watchPath = getConfig('dir', '/fs-watch', config);
    const logger = createLogger(location.name);

    return async (path, res, query) => {
        console.log('watchPathPrefix', watchPathPrefix);
        console.log('watchPath', watchPath);
        const storagePath = watchPathPrefix ? watchPath+path.substring(watchPathPrefix.length) : path;
        console.log('storagePath', storagePath);
        if (!fs.existsSync(storagePath)) {
            return notFound(res, `"${storagePath}" not found`, logger);
        }

        res.sendFile(storagePath);
    }
}
