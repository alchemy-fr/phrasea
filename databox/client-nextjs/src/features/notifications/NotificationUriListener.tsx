'use client';

import {useEffect} from 'react';
import {useRouter, useSearchParams, usePathname} from 'next/navigation';
import {
    BuiltInAttribute,
    quoteAQL,
    searchStateToParams,
} from '@/features/search/searchState';
import {routes} from '@/lib/routes';

/**
 * Resolves a notification URI (`/assets/{id}#discussion-{id}`,
 * `/collections/{id}`, `/workspaces/{id}/manage/{tab}`) to a screen.
 */
export function resolveNotificationUri(uri: string): string {
    const ws = uri.match(/^\/workspaces\/([^/#]+)\/manage\/([^/#]+)$/);
    if (ws) {
        return routes.workspaceManage(ws[1], ws[2]);
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
                    query: `${BuiltInAttribute.Collection} = ${quoteAQL(id)}`,
                },
            ],
        });

        return `${routes.assets()}?${params.toString()}`;
    }

    return routes.assets();
}

/**
 * Handles `/notification-uri?uri=…` deep links coming from emails.
 */
export function NotificationUriListener() {
    const pathname = usePathname();
    const searchParams = useSearchParams();
    const router = useRouter();

    useEffect(() => {
        if (pathname === '/notification-uri') {
            const uri = searchParams.get('uri');
            router.replace(uri ? resolveNotificationUri(uri) : routes.assets());
        }
    }, [pathname, searchParams, router]);

    return null;
}
