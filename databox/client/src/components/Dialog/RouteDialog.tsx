import {ReactElement} from 'react';
import {useLocation, useNavigate} from 'react-router-dom';
import {StateWithBackground} from '../Routing/ModalLink';
import {getPath} from '@alchemy/navigation';

type Props = {
    children(options: {
        open: boolean;
        onClose: () => void;
    }): ReactElement | null;
};

export default function RouteDialog({children}: Props) {
    const {state} = useLocation() as {
        state?: StateWithBackground;
    };

    const navigate = useNavigate();

    const onClose = () => {
        navigate(state?.background || getPath('app'));
    };

    return children({
        open: true,
        onClose,
    });
}
