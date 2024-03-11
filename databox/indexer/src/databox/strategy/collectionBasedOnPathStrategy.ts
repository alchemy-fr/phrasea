import {IndexAsset} from './types';
import {getAlternateUrls} from '../../alternateUrl';
import * as p from 'path';
import {splitPath} from '../../lib/pathUtils';

export const collectionBasedOnPathStrategy: IndexAsset = async (
    asset,
    location,
    databoxClient,
    logger
) => {
    const {path} = asset;

    const alternateUrls = getAlternateUrls(asset, location);

    const branch = splitPath(path);
    branch.pop();

    let collIRI: string;
    try {
        collIRI =
            '/collections/' +
            (await databoxClient.createCollectionTreeBranch(
                asset.workspaceId,
                asset.collectionKeyPrefix ?? '',
                branch.map(k => ({
                    key: k,
                    title: k,
                }))
            ));
    } catch (e: any) {
        logger.error(
            `Failed to create collection branch "${branch.join(
                '/'
            )}": ${e.toString()}`
        );
        throw e;
    }

    try {
        // create real asset
        const assetId = await databoxClient.createAsset({
            sourceFile: asset.publicUrl
                ? {
                      url: asset.publicUrl,
                      isPrivate: asset.isPrivate,
                      alternateUrls,
                      importFile: asset.importFile,
                  }
                : undefined,
            collection: collIRI,
            generateRenditions: asset.generateRenditions,
            key: asset.key,
            title: asset.title || p.basename(path),
            attributes: asset.attributes,
            tags: asset.tags,
            renditions: asset.renditions,
        });
        // also create links into collections
        for (const c of asset.shortcutIntoCollections ?? []) {
            await databoxClient.copyAsset({
                destination: '/collections/' + c,
                ids: [assetId],
                byReference: true,
                withAttributes: false,
                withTags: false,
            });
        }
    } catch (e: any) {
        logger.error(`Failed to create asset "${path}": ${e.toString()}`);
        throw e;
    }
};
