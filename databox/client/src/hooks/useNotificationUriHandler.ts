import {useNavigateToModal} from '../components/Routing/ModalLink.tsx';
import {modalRoutes, routes, Routing} from '../routes.ts';
import type {NotificationUriHandler} from '@alchemy/notification';
import {getPath, useNavigate} from '@alchemy/navigation';
import {BuiltInField, queryToHash} from '../components/Media/Search/search.ts';

export function useNotificationUriHandler(): NotificationUriHandler {
    const navigateToModal = useNavigateToModal();
    const navigate = useNavigate();

    return (uri: string) => {
        const groups = uri.match(/^\/([^/#]+)\/([^/#]+)(?:#(.+))?$/);
        if (groups) {
            const entity = groups[1];
            const id = groups[2];
            const hash = groups[3];

            if (entity === 'assets') {
                navigateToModal(
                    modalRoutes.assets.routes.view,
                    {
                        id: id,
                        renditionId: Routing.UnknownRendition,
                    },
                    undefined,
                    hash
                );

                return;
            } else if (entity === 'collections') {
                const searchHash = queryToHash(
                    undefined,
                    '',
                    [
                        {
                            id: 'collection',
                            query: `${BuiltInField.Collection} = "${id}"`,
                        },
                    ],
                    [],
                    undefined
                );

                navigate(`${getPath(routes.app)}#${searchHash}`);
            }

            return;
        }
    };
}
