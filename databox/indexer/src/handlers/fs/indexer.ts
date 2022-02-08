import {IndexIterator} from "../../indexers";
import {createAsset, getDirConfig, getFiles} from "./shared";
import {FsConfig} from "./types";

export const fsIndexer: IndexIterator<FsConfig> = async function* (
    location
) {
    const {
        watchDir,
        dirPrefix,
        sourceDir
    } = getDirConfig(location.options);

    const iterator = getFiles(watchDir);

    for await (let f of iterator) {
        yield createAsset(
            f,
            location.name,
            watchDir,
            dirPrefix,
            sourceDir
        );
    }
}
