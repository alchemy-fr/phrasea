import {apiClient} from '../init.ts';
import {Asset} from '../types.ts';
import {getPasswordHeaders} from './publicationApi.ts';

export async function loadAsset(id: string): Promise<Asset> {
    return (
        await apiClient.get(`/assets/${id}`, {
            headers: getPasswordHeaders(),
        })
    ).data;
}

let assetLogTimeout: ReturnType<typeof setTimeout>;

export function logAssetView(id: string): void {
    if (assetLogTimeout) {
        clearTimeout(assetLogTimeout);
    }

    assetLogTimeout = setTimeout(() => {
        apiClient.post(`/logs/asset-view/${id}`);
    }, 1000);
}

export function logPublicationView(id: string): void {
    apiClient.post(`/logs/publication-view/${id}`);
}
