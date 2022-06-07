import {RenditionDefinition} from "../types";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";
import apiClient from "./api-client";

type GetOptions = {
    workspaceIds?: string[];
}

export async function getRenditionDefinitions(options: GetOptions = {}): Promise<ApiCollectionResponse<RenditionDefinition>> {
    const res = await apiClient.get('/rendition-definitions', {
        params: options,
    });

    return getHydraCollection(res.data);
}
