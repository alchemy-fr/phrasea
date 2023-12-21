import path from 'path';
import {readdir} from 'fs/promises';
import {Asset} from '../../indexers';
import {generatePublicUrl} from '../../resourceResolver';
import {getConfig, getStrict} from '../../configLoader';
import {FsConfig} from './types';

export async function* getFiles(dir: string): AsyncGenerator<string> {
    const entries = await readdir(dir, {withFileTypes: true});
    for (const entry of entries) {
        const res = path.resolve(dir, entry.name);
        if (entry.isDirectory()) {
            yield* getFiles(res);
        } else {
            yield res;
        }
    }
}

export function createAsset(
    workspaceId: string,
    path: string,
    locationName: string,
    watchDir: string,
    dirPrefix?: string | undefined,
    sourceDir?: string | undefined
): Asset {
    const relativePath = path.substring(watchDir.length);
    const p = dirPrefix ? dirPrefix + relativePath : path;
    const sourcePath = sourceDir ? sourceDir + relativePath : path;

    console.log(
        'generatePublicUrl(p, locationName)',
        generatePublicUrl(p, locationName)
    );

    return {
        workspaceId,
        key: p,
        path: p,
        publicUrl: generatePublicUrl(p, locationName),
        isPrivate: true,
        sourcePath,
    };
}

export function getDirConfig(config: FsConfig) {
    const watchDir = getStrict('dir', config);
    const dirPrefix = getConfig('dirPrefix', undefined, config);
    const sourceDir = getConfig('sourceDir', undefined, config);

    return {
        watchDir,
        dirPrefix,
        sourceDir,
    };
}
