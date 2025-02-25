import React from 'react';
import {useTimeout} from '@alchemy/react-hooks/src/useTimeout';
import SessionAboutToExpireModal from './SessionAboutToExpireModal';
import {useAuth} from '../hooks/useAuth';

type Props = {};

export default function SessionExpireContainer({}: Props) {
    const {tokens} = useAuth();
    const [displayExpire, setDisplayExpire] = React.useState(false);

    const displayExpireModal = React.useCallback(() => {
        if (tokens) {
            setDisplayExpire(true);
        }
    }, [tokens]);

    const onClose = React.useCallback(() => {
        setDisplayExpire(false);
    }, []);

    let delay: number | undefined = undefined;
    if (tokens?.refreshExpiresAt) {
        const beforeEnd = 60000;
        const end = tokens.refreshExpiresAt * 1000 - new Date().getTime();

        if (end < 604800000) {
            // Prevent too high TTL for setTimeout
            delay = Math.max(end - beforeEnd, 5000);
        }
    }

    useTimeout(displayExpireModal, delay);

    return (
        <>{displayExpire && <SessionAboutToExpireModal onClose={onClose} />}</>
    );
}
