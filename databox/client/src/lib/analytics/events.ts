import {trackEvent} from './matomo';

/**
 * Catalogue of the Matomo events (`trackEvent`) sent by the client, documented
 * in doc/tech/Databox/analytics.md.
 *
 * No personal data and no asset/collection title may be sent: `name` is a
 * surface, a type or an attribute key, `value` a number of items.
 */
export const EventCategory = {
    AssetAction: 'Asset action',
    Asset: 'Asset',
    Share: 'Share',
    Collection: 'Collection',
    Basket: 'Basket',
    Story: 'Story',
    Workflow: 'Workflow',
    Integration: 'Integration',
    Discussion: 'Discussion',
    Follow: 'Follow',
    SavedSearch: 'Saved search',
    Search: 'Search',
    DnD: 'DnD',
} as const;

export type EventCategory = (typeof EventCategory)[keyof typeof EventCategory];

export const AssetEvent = {
    Create: 'create',
    Download: 'download',
    Export: 'export',
    Move: 'move',
    Copy: 'copy',
    Delete: 'delete',
    Restore: 'restore',
    Edit: 'edit',
    BatchEdit: 'batch_edit',
    SaveAs: 'save_as',
    ReplaceSource: 'replace_source',
    RemoveShortcut: 'remove_shortcut',
    Upload: 'upload',
    ImportUrls: 'import_urls',
} as const;

export const ShareEvent = {
    Create: 'create',
    Update: 'update',
    Delete: 'delete',
    Download: 'download',
} as const;

export const CollectionEvent = {
    Create: 'create',
    Delete: 'delete',
    Restore: 'restore',
    Move: 'move',
} as const;

export const BasketEvent = {
    Create: 'create',
    Update: 'update',
    Delete: 'delete',
    Archive: 'archive',
    Unarchive: 'unarchive',
    Add: 'add',
    Remove: 'remove',
} as const;

export const StoryEvent = {
    Create: 'create',
    Reorder: 'reorder',
    RemoveItem: 'remove_item',
    SetCover: 'set_cover',
    Pin: 'pin',
    Unpin: 'unpin',
} as const;

export const WorkflowEvent = {
    Trigger: 'trigger',
    Cancel: 'cancel',
    RerunJob: 'rerun_job',
} as const;

export const IntegrationEvent = {
    Run: 'run',
} as const;

export const DiscussionEvent = {
    Send: 'send',
    Edit: 'edit',
    Delete: 'delete',
} as const;

export const FollowEvent = {
    Follow: 'follow',
    Unfollow: 'unfollow',
} as const;

export const SavedSearchEvent = {
    Save: 'save',
    Pin: 'pin',
    Load: 'load',
} as const;

export type TrackOptions = {
    /** Surface, type or key: never a title nor a free text */
    name?: string;
    /** Number of items */
    value?: number;
};

export function track(
    category: EventCategory,
    action: string,
    {name, value}: TrackOptions = {}
): void {
    trackEvent(category, action, name, value);
}
