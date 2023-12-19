import {IndexLocation} from '../../types/config';
import {DataboxClient} from '../../databox/client';
import chokidar from 'chokidar';
import {handleDeleteObject, handlePutObject} from '../../eventHandler';
import {Logger} from 'winston';
import {FsConfig} from './types';
import {createAsset, getDirConfig} from './shared';
import {getStrict} from '../../configLoader';

export async function fsWatcher(
    location: IndexLocation<FsConfig>,
    databoxClient: DataboxClient,
    logger: Logger
) {
    const {watchDir, dirPrefix, sourceDir} = getDirConfig(location.options);

    const workspaceId = await databoxClient.getWorkspaceIdFromSlug(
        getStrict('workspaceSlug', location.options)
    );

    async function storeEvent(eventType: string, path: string): Promise<void> {
        logger.debug(`${eventType}: ${path}`);

        const asset = createAsset(
            workspaceId,
            path,
            location.name,
            watchDir,
            dirPrefix,
            sourceDir
        );

        switch (eventType) {
            case 'add':
                handlePutObject(asset, location, databoxClient, logger);
                break;
            case 'unlink':
                handleDeleteObject(asset, databoxClient, logger);
                break;
        }
    }

    try {
        logger.info(`Watching "${watchDir}"`);
        chokidar
            .watch(watchDir, {
                ignoreInitial: true,
            })
            .on('all', storeEvent);
    } catch (err: any) {
        if (err.name !== 'AbortError') {
            throw err;
        }
    }
}
