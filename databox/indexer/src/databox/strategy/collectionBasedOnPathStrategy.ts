import {IndexAsset} from './types';
import {getAlternateUrls} from '../../alternateUrl';
import p from 'path';
import {splitPath} from '../../lib/pathUtils';

export const collectionBasedOnPathStrategy: IndexAsset = async (
    asset,
    location,
    databoxClient,
    logger
) => {
    const {path} = asset;

    const alternateUrls = getAlternateUrls(asset, location);

    let branch = splitPath(path);
    branch.pop();

    let collIRI: string;
    try {
        collIRI = await databoxClient.createCollectionTreeBranch(
            branch.map(k => ({
                workspaceId: asset.workspaceId,
                key: k,
                title: k,
            }))
        );
    } catch (e: any) {
        logger.error(
            `Failed to create collection branch "${branch.join(
                '/'
            )}": ${e.toString()}`
        );
        throw e;
    }

    try {
        await databoxClient.createAsset({
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
            renditions: asset.renditions,
        });
    } catch (e: any) {
        logger.error(`Failed to create asset "${path}": ${e.toString()}`);
        throw e;
    }
};
