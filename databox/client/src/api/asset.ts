import apiClient from "./api-client";

interface AssetOptions {
    query: string | null;
    workspaces: string[] | null;
}

export async function getAssets(options: AssetOptions) {
    const res = await apiClient.get('/assets', {

    });

    console.log('res.data', res.data);

    return res.data;
}
