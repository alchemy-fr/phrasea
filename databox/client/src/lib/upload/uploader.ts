import {postCollection} from '../../api/collection';
import {UploadFiles} from '../../api/uploader/file';
import {Asset} from '../../types';
import {
    AttributeBatchAction,
    NewAssetPostType,
    postMultipleAssets,
} from '../../api/asset';
import {v4 as uuidv4} from 'uuid';
import {
    CollectionId,
    NewCollectionPath,
    treeViewPathSeparator,
} from '../../components/Media/Collection/CollectionTree/collectionTree.ts';
import {AxiosRequestConfig} from "axios";

type InputFile = {
    title?: string;
    file: File;
    privacy?: number;
    tags?: string[];
    destination: CollectionId;
    uploadToken?: string;
    assetId?: string;
    attributes?: AttributeBatchAction[] | undefined;
};

type UploadInput = {
    files: InputFile[];
};

export async function submitFiles(data: UploadInput, config?: AxiosRequestConfig): Promise<Asset[]> {
    const assets = await createAssets(data, config);

    UploadFiles(
        data.files.map(f => {
            if (!f.assetId) {
                console.log('data', data);
                throw new Error('MAID'); // Missing Asset ID
            }

            return {
                file: f.file,
                data: {
                    targetAsset: f.assetId,
                    uploadToken: f.uploadToken,
                },
            };
        })
    );

    return assets;
}

async function createAssets({files}: UploadInput, config?: AxiosRequestConfig): Promise<Asset[]> {
    const indexedFiles: Record<string, InputFile> = {};
    files.forEach(f => {
        const uploadToken = uuidv4();
        f.uploadToken = uploadToken;
        indexedFiles[uploadToken] = f;
    });

    return await postMultipleAssets(
        files.map((f, i): NewAssetPostType => {
            const data: NewAssetPostType = {
                title: f.title,
                pendingUploadToken: f.uploadToken,
                privacy: f.privacy,
                tags: f.tags,
                sequence: i,
                attributes: f.attributes,
            };

            const dest = f.destination as string;
            if (dest.startsWith('/workspaces/')) {
                data.workspace = dest;
            } else {
                data.collection = dest;
            }

            return data;
        }),
        config
    ).then(assets => {
        return assets.map(a => {
            indexedFiles[a.pendingUploadToken!].assetId = a.id;

            return a;
        });
    });
}

export async function createCollection(
    newCollectionPath: NewCollectionPath
): Promise<string> {
    const {rootId, path} = newCollectionPath;

    const [workspaceId, parentIri] = rootId.split(treeViewPathSeparator);
    let parent = parentIri;
    for (const p of path) {
        parent = (
            await postCollection({
                title: p,
                parent,
                workspace: `/workspaces/${workspaceId}`,
            })
        )['@id'];
    }

    return parent;
}
