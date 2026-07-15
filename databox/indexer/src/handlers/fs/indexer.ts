import {IndexIterator} from '../../indexers';
import {createAsset, getDirConfig, getFiles} from './shared';
import {FsConfig} from './types';
import {getStrict, getConfig} from '../../configLoader';

export const fsIndexer: IndexIterator<FsConfig> = async function* (
    location,
    logger,
    databoxClient
) {
    const {watchDir, dirPrefix, sourceDir} = getDirConfig(location.options);

    const workspaceId = await databoxClient.initWorkspace({
        slug: getStrict('workspaceSlug', location.options),
        flushExisting: getConfig('createNewWorkspace', false, location.options),
        logger,
    });

    const iterator = getFiles(watchDir);

    for await (const f of iterator) {
        yield createAsset(
            workspaceId,
            f,
            location.name,
            watchDir,
            dirPrefix,
            sourceDir
        );
    }
};
