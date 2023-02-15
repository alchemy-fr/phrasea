import {nodeNewPrefix} from "../../components/Media/Collection/EditableTree";
import {
    Collection, NewCollectionPath,
    newCollectionPathSeparator,
    treeViewPathSeparator
} from "../../components/Media/Collection/CollectionsTreeView";
import {postCollection} from "../../api/collection";
import {UploadFiles} from "../../api/uploader/file";
import {Asset} from "../../types";
import {NewAssetPostType, postAsset} from "../../api/asset";
import {v4 as uuidv4} from 'uuid';

type InputFile = {
    title?: string;
    file: File;
    privacy?: number;
    tags?: string[];
    destination: Collection;
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
        if (typeof destination === 'object') {
            destination = await createCollection(destination);
        }

        const data: NewAssetPostType = {
            title: f.title,
            pendingUploadToken: uploadToken,
            privacy: f.privacy,
            tags: f.tags,
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

async function createCollection(newCollectionPath: NewCollectionPath): Promise<string> {
    const {
        rootId,
        path,
    } = newCollectionPath;

    const [workspaceId, parentIri] = rootId.split(treeViewPathSeparator);
    let parent = parentIri;
    for (let p of path) {
        parent = (await postCollection({
            title: p,
            parent,
            workspace: `/workspaces/${workspaceId}`,
        }))['@id'];
    }

    return parent;
}
