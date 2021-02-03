import apiClient from "./api-client";
import {Collection, Workspace} from "../types";

type CollectionOptions = {
    query?: string;
    parent?: string;
    workspaces?: string[];
}

export async function getCollections(options: CollectionOptions): Promise<Collection[]> {
    const res = await apiClient.get('/collections', {
        params: {
            ...options,
        },
    });

    return res.data;
}


export async function getWorkspaces(): Promise<Workspace[]> {
    const collections = await getCollections({});

    const workspaces: {[key: string]: Workspace} = {};

    collections.forEach((c: Collection) => {
        if (!workspaces[c.workspace.id]) {
            workspaces[c.workspace.id] = {
                ...c.workspace,
                collections: [],
            }
        }

        workspaces[c.workspace.id].collections.push(c);
    });

    return (Object.keys(workspaces) as Array<string>).map(i => workspaces[i]);
}
