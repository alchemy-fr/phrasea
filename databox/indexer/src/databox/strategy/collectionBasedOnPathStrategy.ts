import {IndexAsset} from "./types";
import {getAlternateUrls} from "../../alternateUrl";
import p from "path";

export const collectionBasedOnPathStrategy: IndexAsset = async (
    asset,
    location,
    databoxClient,
    logger
) => {
    const {path} = asset;

    const alternateUrls = getAlternateUrls(asset, location);

    let branch = path.replace(/^\//, '').split('/');
    branch.pop();

    let collIRI: string;
    try {
        collIRI = await databoxClient.createCollectionTreeBranch(branch.map(k => ({
            key: k,
            title: k
        })));
    } catch (e) {
        logger.error(`Failed to create collection branch "${branch.join('/')}": ${e.toString()}`);
        throw e;
    }

    try {
        await databoxClient.createAsset({
            source: {
                url: asset.publicUrl,
                isPrivate: asset.isPrivate,
                alternateUrls,
            },
            collection: collIRI,
            generateRenditions: asset.generateRenditions,
            key: asset.key,
            title: p.basename(path),
            attributes: asset.attributes,
            renditions: asset.renditions,
        });
    } catch (e) {
        logger.error(`Failed to create asset "${path}": ${e.toString()}`);
        throw e;
    }
}
