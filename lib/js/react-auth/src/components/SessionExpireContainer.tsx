import React from 'react';
import {useTimeout} from "@alchemy/react-hooks/src/useTimeout";
import SessionAboutToExpireModal from "./SessionAboutToExpireModal";
import {useAuth} from "../hooks/useAuth";

type Props = {};

export default function SessionExpireContainer({}: Props) {
    const {tokens} = useAuth();
    const [displayExpire, setDisplayExpire] = React.useState(false);

    const displayExpireModal = React.useCallback(() => {
        setDisplayExpire(true);
    }, [tokens]);

    const onClose = React.useCallback(() => {
        setDisplayExpire(false);
    }, []);

    let delay: number | undefined = undefined;
    if (tokens) {
        const beforeEnd = 60000;
        const end = tokens.expiresAt * 1000 - new Date().getTime();
        delay = Math.max(end - beforeEnd, 5000)
    }

    useTimeout(displayExpireModal, delay);

    return <>
        {displayExpire && <SessionAboutToExpireModal
            onClose={onClose}
        />}
    </>
}
