import apiClient from "./api-client";
import {AttributeBatchAction} from "./asset";

export type AssetDataTemplate = {
    name: string;
    workspace: string;
    attributes: AttributeBatchAction[] | undefined;
    privacy?: number | undefined;
    tags?: string[] | undefined;
    title?: string | undefined;
}

export async function postAssetDataTemplate(data: AssetDataTemplate): Promise<void> {
    await apiClient.post(`/asset-data-templates`, data);
}
