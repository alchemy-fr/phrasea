import {apiClient} from '../init.ts';

type ExportInput = {
    assets: string[];
    renditions: string[];
};

export async function exportAssets(data: ExportInput): Promise<string> {
    const res = (await apiClient.post(`/export`, data)).data as {
        downloadUrl: string;
    };

    return res.downloadUrl;
}
