/**
 * Realtime channels emitted by the API (see App\Realtime\RealtimeChannel).
 *
 * Every channel is private: subscribing goes through POST /pusher/auth, which
 * grants it to whoever may read the related object.
 */

export const workspaceChannel = (workspaceId: string) =>
    `private-workspace-${workspaceId}`;

export const assetChannel = (assetId: string) => `private-asset-${assetId}`;

export const fileChannel = (fileId: string) => `private-file-${fileId}`;

export const basketChannel = (basketId: string) => `private-basket-${basketId}`;

export const exportChannel = (exportId: string) => `private-export-${exportId}`;

export const workflowChannel = (workflowId: string) =>
    `private-workflow-${workflowId}`;

/**
 * @param threadKey `<objectKey>:<objectId>`, normalized on subscription
 */
export const threadChannel = (threadKey: string) =>
    `private-thread-${threadKey}`;

export const RENDITION_UPDATE_EVENT = 'rendition-update';
