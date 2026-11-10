/**
 * Realtime channels emitted by the API (see App\Realtime\RealtimeChannel).
 *
 * Every channel is private: subscribing goes through POST /pusher/auth, which
 * grants it to whoever may read the related object.
 */

/** Pusher prefix of the channels needing an authorization (see PusherManager::PRIVATE_PREFIX) */
const PRIVATE_PREFIX = 'private-';

export const workspaceChannel = (workspaceId: string) =>
    `${PRIVATE_PREFIX}workspace-${workspaceId}`;

export const assetChannel = (assetId: string) =>
    `${PRIVATE_PREFIX}asset-${assetId}`;

export const fileChannel = (fileId: string) =>
    `${PRIVATE_PREFIX}file-${fileId}`;

export const basketChannel = (basketId: string) =>
    `${PRIVATE_PREFIX}basket-${basketId}`;

export const exportChannel = (exportId: string) =>
    `${PRIVATE_PREFIX}export-${exportId}`;

export const workflowChannel = (workflowId: string) =>
    `${PRIVATE_PREFIX}workflow-${workflowId}`;

/**
 * @param threadKey `<objectKey>:<objectId>`, normalized on subscription
 */
export const threadChannel = (threadKey: string) =>
    `${PRIVATE_PREFIX}thread-${threadKey}`;

export const RENDITION_UPDATE_EVENT = 'rendition-update';
