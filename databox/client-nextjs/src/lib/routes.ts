/**
 * Central route builders. Dialog screens (manage, view...) are real routes so
 * they can be shared and restored from the URL; they are rendered as
 * intercepting routes above the search screen when navigated to client-side.
 */
export const UNKNOWN_RENDITION = '_';

export const routes = {
    home: () => '/',
    assets: () => '/assets',
    assetView: (id: string, renditionId = UNKNOWN_RENDITION, hash = '') =>
        `/assets/${id}/${renditionId}${hash}`,
    assetManage: (id: string, tab = 'info') => `/assets/${id}/manage/${tab}`,
    fileManage: (id: string, tab = 'info') => `/files/${id}/manage/${tab}`,
    collectionManage: (id: string, tab = 'info') =>
        `/collections/${id}/manage/${tab}`,
    workspaceManage: (id: string, tab = 'info') =>
        `/workspaces/${id}/manage/${tab}`,
    basketView: (id: string) => `/baskets/${id}/view`,
    basketManage: (id: string, tab = 'info') => `/baskets/${id}/manage/${tab}`,
    savedSearchManage: (id: string, tab = 'info') =>
        `/saved-searches/${id}/manage/${tab}`,
    profileManage: (id: string, tab = 'info') =>
        `/profiles/${id}/manage/${tab}`,
    workflow: (id: string) => `/workflows/${id}`,
    attributesEditor: () => '/attributes/editor',
    operationTasks: () => '/admin/tasks',
    operationTaskNew: () => '/admin/tasks/new',
    operationTaskRun: (task: string) => `/admin/tasks/${task}/run`,
    operationTaskDetails: (id: string) => `/admin/tasks/${id}/details`,
    pages: () => '/pages',
    pageEdit: (id: string) => `/pages/${id}/edit`,
    page: (slug: string) => `/p/${slug}`,
    share: (id: string, token: string) => `/s/${id}/${token}`,
};

export type WorkspaceTab =
    | 'info'
    | 'edit'
    | 'permissions'
    | 'tags'
    | 'entities'
    | 'attributes'
    | 'attribute-policies'
    | 'renditions'
    | 'rendition-policies'
    | 'asset-policies'
    | 'integrations'
    | 'filter-rules';
