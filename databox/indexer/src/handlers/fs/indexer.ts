import {IndexIterator} from "../../indexers";
import {createAsset, getDirConfig, getFiles} from "./shared";
import {FsConfig} from "./types";
import {getStrict} from "../../configLoader";

export const fsIndexer: IndexIterator<FsConfig> = async function* (
    location,
    logger,
    databoxClient
) {
    const {
        watchDir,
        dirPrefix,
        sourceDir
    } = getDirConfig(location.options);

    const workspaceId = await databoxClient.getWorkspaceIdFromSlug(getStrict('workspaceSlug', location.options));

    const iterator = getFiles(watchDir);

    for await (let f of iterator) {
        yield createAsset(
            workspaceId,
            f,
            location.name,
            watchDir,
            dirPrefix,
            sourceDir
        );
    }
}
