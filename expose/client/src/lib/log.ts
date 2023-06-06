import apiClient from "./apiClient";
import config from "./config";

let assetLogTimeout: ReturnType<typeof setTimeout>;

export function logAssetView(id: string): void {
    if (assetLogTimeout) {
        clearTimeout(assetLogTimeout);
    }

    assetLogTimeout = setTimeout(() => {
        apiClient.post(`${config.getApiBaseUrl()}/logs/asset-view/${id}`);
    }, 1000);
}

export function logPublicationView(id: string): void {
    apiClient.post(`${config.getApiBaseUrl()}/logs/publication-view/${id}`);
}
