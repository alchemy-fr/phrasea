import apiClient from "./api-client";

interface AssetOptions {
    query?: string;
    workspaces?: string[];
    parents?: string[];
}

export async function getAssets(options: AssetOptions) {
    const res = await apiClient.get('/assets', {
        params: options,
    });

    console.log('res.data', res.data);

    return res.data;
}
