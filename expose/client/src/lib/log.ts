import apiClient from "./api-client";

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
