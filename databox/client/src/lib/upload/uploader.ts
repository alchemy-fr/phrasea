import {nodeNewPrefix} from "../../components/Media/Collection/EditableTree";
import {newCollectionPathSeparator, treeViewPathSeparator} from "../../components/Media/Collection/CollectionsTreeView";
import {postCollection} from "../../api/collection";
import {UploadFiles} from "../../api/uploader/file";
import {Asset} from "../../types";
import {NewAssetPostType, postAsset} from "../../api/asset";
import { v4 as uuidv4 } from 'uuid';

type InputFile = {
    title?: string;
    file: File;
    privacy?: number;
    destination: string;
    uploadToken?: string;
    assetId?: string;
};

type UploadInput = {
    files: InputFile[];
}

export async function submitFiles(userId: string, data: UploadInput): Promise<Asset[]> {
    const assets = await createAssets(data);

    UploadFiles(userId, data.files.map(f => {
        return {
            file: f.file,
            data: {
                targetAsset: f.assetId,
                uploadToken: f.uploadToken,
            }
        };
    }));

    return assets;
}

async function createAssets({files}: UploadInput): Promise<Asset[]> {

    return await Promise.all(files.map(async (f): Promise<Asset> => {
        const uploadToken = uuidv4();
        f.uploadToken = uploadToken;

        let destination = f.destination;
        if (destination.startsWith(nodeNewPrefix)) {
            destination = await createCollection(destination);
        }

        const data: NewAssetPostType = {
            title: f.title,
            pendingUploadToken: uploadToken,
            privacy: f.privacy,
        };

        if (destination.startsWith('/workspaces/')) {
            data.workspace = destination;
        } else {
            data.collection = destination;
        }

        return await postAsset(data).then(a => {
            f.assetId = a.id;

            return a;
        });
    }));
}

async function createCollection(newCollectionPath: string): Promise<string> {
    const [parentPath, ...rest] = newCollectionPath.substring(nodeNewPrefix.length).split(newCollectionPathSeparator);
    const [workspaceId, parentIri] = parentPath.split(treeViewPathSeparator);
    let parent = parentIri;
    for (let p of rest) {
        parent = (await postCollection({
            title: p,
            parent,
            workspace: `/workspaces/${workspaceId}`,
        }))['@id'];
    }

    return parent;
}
