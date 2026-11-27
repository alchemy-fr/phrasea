import {
    BuiltInAttribute,
    quoteAQL,
    searchStateToParams,
} from '@/features/search/searchState';
import {routes} from '@/lib/routes';

/**
 * Resolves a notification URI (`/assets/{id}#discussion-{id}`,
 * `/collections/{id}`, `/saved-searches/{id}`, `/quarantine/{id}`,
 * `/workspaces/{id}/manage/{tab}`, `/workspaces/{id}/manage/{tab}/{item}…`,
 * `/admin/integrations/{id}`) to a screen.
 */
export function resolveNotificationUri(uri: string): string {
    const ws = uri.match(
        /^\/workspaces\/([^/#?]+)\/manage\/([^/#?]+)((?:\/[^/#?]+)*)$/
    );
    if (ws) {
        return `${routes.workspaceManage(ws[1], ws[2])}${ws[3]}`;
    }
    const instanceIntegration = uri.match(/^\/admin\/integrations\/([^/#?]+)$/);
    if (instanceIntegration) {
        return `${routes.instanceIntegrations()}/${instanceIntegration[1]}`;
    }
    const m = uri.match(/^\/([^/#]+)\/([^/#]+)(?:#(.+))?$/);
    if (!m) {
        return routes.assets();
    }
    const [, entity, id, hash] = m;
    if (entity === 'assets') {
        return routes.assetView(id, undefined, hash ? `#${hash}` : undefined);
    }
    if (entity === 'collections') {
        const params = searchStateToParams({
            query: '',
            sortBy: [],
            conditions: [
                {
                    id: BuiltInAttribute.Collection,
                    query: `${BuiltInAttribute.Collection} IS ${quoteAQL(id)}`,
                },
            ],
        });

        return `${routes.assets()}?${params.toString()}`;
    }
    if (entity === 'quarantine') {
        return routes.quarantine(id);
    }
    if (entity === 'saved-searches') {
        return `${routes.assets()}?${new URLSearchParams({id}).toString()}`;
    }

    return routes.assets();
}

export const NOTIFICATION_URI_PATH = '/notification-uri';

/**
 * The screen a `/notification-uri?uri=…` deep link (from the emails) leads to.
 */
export function notificationTarget(searchParams: URLSearchParams): string {
    const uri = searchParams.get('uri');

    return uri ? resolveNotificationUri(uri) : routes.assets();
}

/**
 * Where the proxy redirects a request to: the screen of a notification deep
 * link, or null for any other path.
 */
export function notificationRedirect(
    pathname: string,
    searchParams: URLSearchParams
): string | null {
    return pathname === NOTIFICATION_URI_PATH
        ? notificationTarget(searchParams)
        : null;
}
