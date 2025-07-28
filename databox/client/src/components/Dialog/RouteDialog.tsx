import {ReactElement} from 'react';
import {useCloseModal, useNavigateToModal} from '../Routing/ModalLink';
import type {NavigateToOverlayProps} from '@alchemy/navigation';

type Props = {
    previousLocation?: NavigateToOverlayProps | undefined;
    children(options: {
        open: boolean;
        onClose: () => void;
    }): ReactElement | null;
};

export default function RouteDialog({children, previousLocation}: Props) {
    const closeModal = useCloseModal();
    const navigateToModal = useNavigateToModal();

    return children({
        open: true,
        onClose: previousLocation
            ? () =>
                  navigateToModal(
                      previousLocation.route,
                      previousLocation.params,
                      previousLocation.options,
                      previousLocation.hash
                  )
            : closeModal,
    });
}
