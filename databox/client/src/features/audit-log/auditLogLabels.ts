import type {TFunction} from 'i18next';
import {routes} from '@/lib/routes';

/** English labels of the actions, by key (`audit_log.action.<key>`) */
export const AUDIT_LOG_ACTIONS: Record<string, string> = {
    asset_moved: 'Asset moved',
    asset_added_to_collection: 'Asset added to a collection',
    asset_removed_from_collection: 'Asset removed from a collection',
    asset_substituted: 'Asset file substituted',
    asset_attribute_changed: 'Attributes changed',
    object_created: 'Created',
    object_updated: 'Updated',
    object_deleted: 'Deleted',
    object_trashed: 'Moved to the trash',
    object_restored: 'Restored from the trash',
    collection_moved: 'Collection moved',
    asset_quarantine_bypassed: 'Quarantine bypassed',
    basket_archived: 'Basket archived',
    basket_unarchived: 'Basket unarchived',
    basket_assets_added: 'Assets added to a basket',
    basket_assets_removed: 'Assets removed from a basket',
    profile_updated: 'Profile items changed',
    rendition_deleted: 'Rendition deleted',
    asset_copied: 'Asset copied',
    permission_granted: 'Permission granted',
    permission_changed: 'Permission changed',
    permission_revoked: 'Permission revoked',
    asset_downloaded: 'Asset downloaded',
    workflow_triggered: 'Workflow triggered',
    workflow_job_rerun: 'Workflow job rerun',
    workflow_cancelled: 'Workflow cancelled',
    automation_triggered: 'Automation triggered',
    workspace_terms_signed: 'Terms & Conditions signed',
};

/** English labels of the object types, by key (`audit_log.object_type.<key>`) */
export const AUDIT_LOG_OBJECT_TYPES: Record<string, string> = {
    asset: 'Asset',
    collection: 'Collection',
    workspace: 'Workspace',
    basket: 'Basket',
    share: 'Share',
    saved_search: 'Saved search',
    saved_search_automation: 'Automation',
    profile: 'Profile',
    page: 'Page',
    asset_data_template: 'Asset data template',
    workspace_template: 'Workspace template',
    attribute_definition: 'Attribute definition',
    attribute_policy: 'Attribute policy',
    attribute_filter_rule: 'Attribute filter rule',
    entity_list: 'Entity list',
    attribute_entity: 'Entity',
    rendition_definition: 'Rendition definition',
    rendition_policy: 'Rendition policy',
    tag: 'Tag',
    workspace_env: 'Workspace environment variable',
    workspace_integration: 'Integration',
    workspace_secret: 'Workspace secret',
};

export function auditLogActionLabel(key: string, t: TFunction): string {
    return t(`audit_log.action.${key}`, AUDIT_LOG_ACTIONS[key] ?? key);
}

export function auditLogObjectTypeLabel(key: string, t: TFunction): string {
    return t(
        `audit_log.object_type.${key}`,
        AUDIT_LOG_OBJECT_TYPES[key] ?? key
    );
}

/** Where an object still existing is shown, when it has a page */
export function auditLogObjectHref(
    objectType: string | null | undefined,
    objectId: string | null | undefined
): string | undefined {
    if (!objectType || !objectId) {
        return undefined;
    }

    switch (objectType) {
        case 'asset':
            return routes.assetView(objectId);
        case 'collection':
            return routes.collectionManage(objectId);
        case 'workspace':
            return routes.workspaceManage(objectId);
        case 'basket':
            return routes.basketView(objectId);
        case 'saved_search':
            return routes.savedSearchManage(objectId);
        case 'profile':
            return routes.profileManage(objectId);
        default:
            return undefined;
    }
}
