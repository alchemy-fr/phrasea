import {IndexAsset} from "./types";
import {getAlternateUrls} from "../../alternateUrl";
import p from "path";
import {AxiosError} from "axios";

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
        debugError(e);
        logger.error(`Failed to create collection branch "${branch.join('/')}": ${e.toString()}`);
        throw e;
    }

    try {
        await databoxClient.createAsset({
            source: {
                url: asset.publicUrl,
                isPrivate: true,
                alternateUrls,
            },
            collection: collIRI,
            key: path,
            title: p.basename(path),
        });
    } catch (e) {
        debugError(e);
        logger.error(`Failed to create asset "${path}": ${e.toString()}`);
        throw e;
    }
}

function debugError(error: AxiosError) {
    if (error.response) {
        console.debug(error.response.data);
        console.debug(error.response.status);
        console.debug(error.response.headers);
    }
}
