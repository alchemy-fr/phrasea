import {useNavigateToModal} from "../components/Routing/ModalLink.tsx";
import {modalRoutes} from "../routes.ts";
import type {NotificationUriHandler} from "@alchemy/notification";

export function useNotificationUriHandler(): NotificationUriHandler {
    const navigateToModal = useNavigateToModal();

    return (uri: string) => {
        const groups = uri.match(/^\/assets\/([^/#]+)(?:#(.+))?$/);
        if (groups) {
            const assetId = groups[1];
            const hash = groups[2];
            navigateToModal(
                modalRoutes.assets.routes.viewGuessRendition,
                {
                    id: assetId,
                },
                undefined,
                hash,
            );

            return;

        }
    };
}
