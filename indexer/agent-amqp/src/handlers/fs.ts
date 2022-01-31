import {IndexLocation} from "../types/config";
import {DataboxClient} from "../lib/databox/client";
import {declareAssetServer} from "../server";
import chokidar from "chokidar";
import {getConfig} from "../configLoader";
import {handleDeleteObject, handlePutObject} from "../listener/eventHandler";
import {generatePublicUrl} from "../resourceResolver";

export function fsHandler(location: IndexLocation, databoxClient: DataboxClient) {
    const config = location.options || {};

    const watchPathPrefix = getConfig('dirPrefix', '/fs-watch', config);
    const watchPath = getConfig('dir', '/fs-watch', config);

    function storeEvent(eventType: string, path: string): Promise<void> {
        console.log(eventType, path);

        switch (eventType) {
            case 'add':
                return handlePutObject(generatePublicUrl(path, location.name), path, databoxClient);
            case 'unlink':
                return handleDeleteObject(path, databoxClient);
        }
    }

    try {
        chokidar.watch(watchPath, {
            ignoreInitial: true,
        }).on('all', (event, filename) => {
            const realPath = watchPathPrefix+filename.substring(watchPath.length);
            storeEvent(event, realPath);
        });
    } catch (err) {
        if (err.name === 'AbortError')
            return;
        throw err;
    }

    declareAssetServer(location.name, async (path, res, query) => {
        const storagePath = watchPath+path.substring(watchPathPrefix.length);;
        res.sendFile(storagePath);
    });
}
