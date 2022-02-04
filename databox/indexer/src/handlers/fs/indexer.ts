import {IndexIterator} from "../../indexers";
import {getFiles} from "./shared";
import {FsConfig} from "./types";
import {generatePublicUrl} from "../../resourceResolver";

export const fsIndexer: IndexIterator<FsConfig> = async function *(
    location
) {
    const iterator = getFiles(location.options.dir);
    for await (let f of iterator) {
        yield {
            path: f,
            publicUrl: generatePublicUrl(f, location.name),
        }
    }
}
