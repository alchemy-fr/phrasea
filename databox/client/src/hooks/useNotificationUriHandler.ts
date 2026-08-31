import {useNavigateToModal} from '../components/Routing/ModalLink.tsx';
import {modalRoutes, routes, Routing} from '../routes.ts';
import type {NotificationUriHandler} from '@alchemy/notification';
import {
    getPath,
    RouteDefinition,
    RouteParameters,
    To,
    useNavigate,
} from '@alchemy/navigation';
import {
    BuiltInAttributeEnum,
    queryToHash,
} from '../components/Media/Search/search.ts';

type ModalTarget = {
    type: 'modal';
    route: RouteDefinition;
    params: RouteParameters;
    hash?: string;
};

type PathTarget = {
    type: 'path';
    path: string;
};

export type NotificationTarget = ModalTarget | PathTarget;

/**
 * Resolves a notification URI (e.g. `/assets/{id}#discussion-{id}`) to the
 * screen it points at. Used both when clicking an in-app notification and when
 * landing on the `notification-uri` route from an email link.
 */
export function resolveNotificationUri(
    uri: string
): NotificationTarget | undefined {
    const wsManage = uri.match(/^\/workspaces\/([^/#]+)\/manage\/([^/#]+)$/);
    if (wsManage) {
        return {
            type: 'modal',
            route: modalRoutes.workspaces.routes.manage,
            params: {
                id: wsManage[1],
                tab: wsManage[2],
            },
        };
    }

    const groups = uri.match(/^\/([^/#]+)\/([^/#]+)(?:#(.+))?$/);
    if (!groups) {
        return undefined;
    }

    const entity = groups[1];
    const id = groups[2];
    const hash = groups[3];

    if (entity === 'assets') {
        return {
            type: 'modal',
            route: modalRoutes.assets.routes.view,
            params: {
                id: id,
                renditionId: Routing.UnknownRendition,
            },
            hash,
        };
    }

    if (entity === 'collections') {
        const searchHash = queryToHash(
            undefined,
            '',
            [
                {
                    id: 'collection',
                    query: `${BuiltInAttributeEnum.Collection} = "${id}"`,
                },
            ],
            [],
            undefined
        );

        return {
            type: 'path',
            path: `${getPath(routes.assets)}#${searchHash}`,
        };
    }

    return undefined;
}

/**
 * Builds a standalone location for a target, unlike
 * {@link useNotificationUriHandler} which opens modals on top of the current
 * page.
 */
export function getNotificationTargetLocation(target: NotificationTarget): To {
    if (target.type === 'path') {
        return target.path;
    }

    const searchParams = new URLSearchParams();
    searchParams.set('_m', getPath(target.route, target.params));

    return {
        pathname: getPath(routes.assets),
        search: searchParams.toString(),
        hash: target.hash,
    };
}

export function useNotificationUriHandler(): NotificationUriHandler {
    const navigateToModal = useNavigateToModal();
    const navigate = useNavigate();

    return (uri: string) => {
        const target = resolveNotificationUri(uri);
        if (!target) {
            return;
        }

        if (target.type === 'modal') {
            navigateToModal(
                target.route,
                target.params,
                undefined,
                target.hash
            );

            return;
        }

        navigate(target.path);
    };
}
