import {apiClient} from '../init.ts';
import {AssetExport} from '../types.ts';

type ExportInput = {
    assets: string[];
    renditions: string[];
};

export async function exportAssets(data: ExportInput): Promise<AssetExport> {
    return (await apiClient.post(`/asset-exports`, data)).data;
}
