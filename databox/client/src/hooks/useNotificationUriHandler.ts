import {useNavigateToModal} from '../components/Routing/ModalLink.tsx';
import {modalRoutes, routes} from '../routes.ts';
import type {NotificationUriHandler} from '@alchemy/notification';
import {getPath, useNavigate} from '@alchemy/navigation';
import {BuiltInFilter, queryToHash} from "../components/Media/Search/search.ts";
import {useTranslation} from 'react-i18next';

export function useNotificationUriHandler(): NotificationUriHandler {
    const navigateToModal = useNavigateToModal();
    const navigate = useNavigate();
    const {t} = useTranslation();

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
                    },
                    undefined,
                    hash
                );

                return;
            } else if (entity === 'collections') {
                const searchHash = queryToHash('', [{
                    a: BuiltInFilter.Collection,
                    t: t('search_provider.collections', `Collections`),
                    v: [
                        {
                            label: id,
                            value: '/' + id,
                        },
                    ],
                }], [], undefined);

                navigate(`${getPath(routes.app)}#${searchHash}`);
            }

            return;
        }
    };
}
