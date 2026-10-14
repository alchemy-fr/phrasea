/**
 * Fixture tree for the end-to-end suite, and the expectations derived from it.
 * Both come from the same two arrays, so the files on disk and the assertions
 * cannot drift apart.
 *
 * The expectations are expressed the way the databox API exposes things: a
 * collection by its path of names, an asset by the `indexer://` alternate URL
 * of its source file. Neither the asset key nor the collection key is part of
 * the API output.
 */

/**
 * The `dirPrefix` of the e2e location, which is also the name of the root
 * collection the indexer creates.
 */
export const PREFIX = 'e2e';

/** Files that must end up indexed. */
export const FILES_INDEXED = [
    'root.txt',
    'level1/visible.txt',
    'level1/level2/deep.txt',
    'Dossier Accentué/Été 2024.txt',
];

/**
 * Files the blacklist `(^|/)\..+$` of the generated config must reject: one
 * hidden file at the root, one regular file inside a hidden directory.
 */
export const FILES_BLACKLISTED = ['.hidden-file.txt', '.hidden/ignored.txt'];

export function fileContent(relativePath: string): string {
    return `e2e fixture ${relativePath}\n`;
}

function dirOf(relativePath: string): string {
    const i = relativePath.lastIndexOf('/');

    return i < 0 ? '' : relativePath.slice(0, i);
}

/**
 * The collection an indexed file lands in, as a path of collection names —
 * what collectionBasedOnPathStrategy derives from dirPrefix + the relative
 * path, minus the file name.
 */
export function collectionOf(relativePath: string): string {
    const dir = dirOf(relativePath);

    return '' === dir ? `/${PREFIX}` : `/${PREFIX}/${dir}`;
}

/** The `indexer://` alternate URL the location advertises for a file. */
export function sourceUrl(relativePath: string, sourceDir: string): string {
    return `indexer://${sourceDir}/${relativePath}`;
}

/** Every collection that must exist, as a path of names. */
export function expectedCollectionPaths(): string[] {
    const paths = new Set<string>();

    for (const relativePath of FILES_INDEXED) {
        const dir = dirOf(relativePath);
        const segments = [PREFIX].concat('' === dir ? [] : dir.split('/'));

        let path = '';
        for (const segment of segments) {
            path += `/${segment}`;
            paths.add(path);
        }
    }

    return [...paths];
}

/** `<source url>|<collection path>`, one per expected asset. */
export function expectedAssets(sourceDir: string): string[] {
    return FILES_INDEXED.map(
        relativePath =>
            `${sourceUrl(relativePath, sourceDir)}|${collectionOf(relativePath)}`
    );
}

/** The blacklisted asset keys, as pathFilter logs them. */
export function blacklistedKeys(): string[] {
    return FILES_BLACKLISTED.map(relativePath => `${PREFIX}/${relativePath}`);
}
