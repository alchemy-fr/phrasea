import React from "react";
import AuthenticationContext from "../context/AuthenticationContext";
import {getFullPath, useModals} from '@alchemy/navigation'
import SessionAboutToExpire from "../components/SessionAboutToExpire";

type Options = {
    noticeBeforeEnd?: number;
    idleTimeout?: number;
}

export function useSessionExpire({
    noticeBeforeEnd = 60000,
    idleTimeout = 60000,
}: Options) {
    const {openModal} = useModals();
    const {tokens, logout} = React.useContext(AuthenticationContext);
    const sessionTimeout = React.useRef<ReturnType<typeof setTimeout> | null>(null);
    const promptTimeout = React.useRef<ReturnType<typeof setTimeout> | null>(null);
    const idle = React.useRef<boolean>(false);
    const sessionExpiredToastId = 'sess_exp';

    const sessionExpiredToast = () => {
        toast.warning(t('auth:session_expired', 'Your session has expired'), {
            toastId: sessionExpiredToastId,
        });
    };

    const cancelExpiration = () => {
        if (sessionTimeout.current) {
            clearTimeout(sessionTimeout.current);
            sessionTimeout.current = null;
        }
        if (promptTimeout.current) {
            clearTimeout(promptTimeout.current);
            promptTimeout.current = null;
        }
    }

    React.useEffect(() => {
        const unregisterIdle = listenIdle((isIdle) => {
            idle.current = isIdle;
        }, idleTimeout);

        return () => {
            unregisterIdle();
        }
    }, [idleTimeout]);

    React.useEffect(() => {
        if (tokens.refreshExpiresIn) {
            sessionTimeout.current = setTimeout(() => {
                sessionExpiredToast();
                logout(getFullPath());
            }, tokens.refreshExpiresIn);

            promptTimeout.current = setTimeout(() => {
                if (idle.current) {
                    openModal(SessionAboutToExpire, {
                        expiresIn: noticeBeforeEnd,
                        cancelLogout: () => {
                            cancelExpiration();
                        }
                    });
                } else {
                    cancelExpiration();
                    await refreshToken();
                    refreshUser(getAuthUser()!);
                }
            }, tokens.refreshExpiresIn - noticeBeforeEnd);
        }
    }, [tokens]);

    React.useEffect(() => {
        const listener = () => {
            cancelExpiration();
        };
        addLogoutListener(listener);

        return () => {
            removeLogoutListener(listener);
        }
    }, [])
}


function listenIdle(onChange: (isIdle: boolean) => void, idleTimeout: number) {
    let time: ReturnType<typeof setTimeout>;

    function resetTimer() {
        onChange(false);
        clearTimeout(time);
        time = setTimeout(() => {
            onChange(true);
        }, idleTimeout);
    }

    window.addEventListener('load', resetTimer);
    document.addEventListener('mousemove', resetTimer);
    document.addEventListener('keydown', resetTimer);
    document.addEventListener('touchstart', resetTimer);

    return () => {
        clearTimeout(time);
        window.removeEventListener('load', resetTimer);
        document.removeEventListener('mousemove', resetTimer);
        document.removeEventListener('keydown', resetTimer);
        document.removeEventListener('touchstart', resetTimer);
    };
}
