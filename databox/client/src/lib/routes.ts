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
    fileManage: (id: string, tab = 'info') => `/files/${id}/manage/${tab}`,
    collectionManage: (id: string, tab = 'info') =>
        `/collections/${id}/manage/${tab}`,
    workspaceManage: (id: string, tab = 'info') =>
        `/workspaces/${id}/manage/${tab}`,
    /** Values grid of an entity list (nested panel of the `entities` tab) */
    entityListValues: (workspaceId: string, listId: string) =>
        `/workspaces/${workspaceId}/manage/entities/${listId}/manage`,
    /** Full-page exploration of the values of an entity list, with their assets */
    entityListExplore: (listId: string) => `/entity-lists/${listId}/explore`,
    basketView: (id: string) => `/baskets/${id}/view`,
    basketManage: (id: string, tab = 'info') => `/baskets/${id}/manage/${tab}`,
    savedSearchManage: (id: string, tab = 'info') =>
        `/saved-searches/${id}/manage/${tab}`,
    profileManage: (id: string, tab = 'general') =>
        `/profiles/${id}/manage/${tab}`,
    workflow: (id: string) => `/workflows/${id}`,
    /** A run with one of its jobs selected */
    workflowJob: (id: string, jobId: string) =>
        `/workflows/${id}/jobs/${encodeURIComponent(jobId)}`,
    /** Every workflow run (admins) */
    workflows: () => '/admin/workflows',
    attributesEditor: () => '/attributes/editor',
    quarantine: (assetId?: string) =>
        assetId ? `/quarantine?asset=${assetId}` : '/quarantine',
    operationTasks: () => '/admin/tasks',
    operationTaskNew: () => '/admin/tasks/new',
    operationTaskRun: (task: string) => `/admin/tasks/${task}/run`,
    operationTaskDetails: (id: string) => `/admin/tasks/${id}/details`,
    themeSettings: () => '/admin/theme',
    instanceIntegrations: () => '/admin/integrations',
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

/**
 * Routes rendered in the `@modal` slot when navigated to client-side: they
 * open above the current screen, which must stay as it is behind them. Keep
 * in sync with the intercepting routes of `app/(app)/@modal`.
 */
const stackedRoute =
    /^\/(assets\/[^/]+\/[^/]+|attributes\/editor|(baskets|collections|files|profiles|saved-searches|workspaces)\/[^/]+\/manage|workflows\/[^/]+)(\/|$)/;

export function isStackedRoute(pathname: string): boolean {
    return stackedRoute.test(pathname);
}

const basketViewRoute = /^\/baskets\/([^/]+)\/view\/?$/;

export function matchBasketView(pathname: string): string | undefined {
    return basketViewRoute.exec(pathname)?.[1];
}
