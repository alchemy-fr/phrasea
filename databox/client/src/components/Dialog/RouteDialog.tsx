import {ReactElement} from 'react';
import {useCloseModal} from '../Routing/ModalLink';

type Props = {
    children(options: {
        open: boolean;
        onClose: () => void;
    }): ReactElement | null;
};

export default function RouteDialog({children}: Props) {
    const closeModal = useCloseModal();

    return children({
        open: true,
        onClose: closeModal,
    });
}
