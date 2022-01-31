import {IndexAsset} from "./types";
import {getAlternateUrls} from "../../../alternateUrl";
import p from "path";
import {DataboxClient} from "../client";
import {AxiosError} from "axios";

export const collectionBasedOnPathStrategy: IndexAsset = async (publicUrl: string, databoxClient: DataboxClient, path: string) => {
    const alternateUrls = getAlternateUrls(path);

    let branch = path.split('/');
    branch.pop();

    let collIRI: string;
    try {
        collIRI = await databoxClient.createCollectionTreeBranch(branch.map(k => ({
            key: k,
            title: k
        })));
    } catch (e) {
        debugError(e);
        console.error(`Failed to create collection branch "${branch.join('/')}": ${e.toString()}`);
        throw e;
    }

    try {
        await databoxClient.createAsset({
            source: {
                url: publicUrl,
                isPrivate: true,
                alternateUrls,
            },
            collection: collIRI,
            key: path,
            title: p.basename(path),
        });
    } catch (e) {
        debugError(e);
        console.error(`Failed to create asset "${path}": ${e.toString()}`);
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
