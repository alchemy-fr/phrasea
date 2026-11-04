export function assetImportsQueryKey(workspaceId: string) {
    return ['asset-imports', workspaceId];
}

export function assetImportErrorsQueryKey(importId: string) {
    return ['asset-import-errors', importId];
}
