import {IndexLocation} from "../../types/config";
import {DataboxClient} from "../../databox/client";
import {declareAssetServer} from "../../server";
import chokidar from "chokidar";
import {getConfig} from "../../configLoader";
import {handleDeleteObject, handlePutObject} from "../../eventHandler";
import {generatePublicUrl} from "../../resourceResolver";
import {Logger} from "winston";
import {FsConfig} from "./types";

export function fsWatcher(location: IndexLocation<FsConfig>, databoxClient: DataboxClient, logger: Logger) {
    const config = location.options;

    const watchPathPrefix = getConfig('dirPrefix', undefined, config);
    const watchPath = getConfig('dir', '/fs-watch', config);

    function storeEvent(eventType: string, path: string): Promise<void> {
        logger.debug(`${eventType}: ${path}`);

        switch (eventType) {
            case 'add':
                return handlePutObject(generatePublicUrl(path, location.name), path, databoxClient, logger);
            case 'unlink':
                return handleDeleteObject(path, databoxClient, logger);
        }
    }

    try {
        logger.info(`Watching "${watchPath}"`);
        chokidar.watch(watchPath, {
            ignoreInitial: true,
        }).on('all', (event, filename) => {
            const realPath = (watchPathPrefix || watchPath) + filename.substring(watchPath.length);
            storeEvent(event, realPath);
        });
    } catch (err) {
        if (err.name === 'AbortError')
            return;
        throw err;
    }

    declareAssetServer(location.name, async (path, res, query) => {
        const storagePath = watchPathPrefix ? watchPath+path.substring(watchPathPrefix.length) : path;
        res.sendFile(storagePath);
    });
}
