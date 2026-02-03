import React, {useEffect} from 'react';
import {useRequestErrorHandler} from '@alchemy/api';
import {toast} from 'react-toastify';
import {useAuth} from '@alchemy/react-auth';
import {AuthUser} from '@alchemy/auth';
import {useMatomo} from '@jonkoops/matomo-tracker-react';
import {setSentryUser} from '../../../core';
import {UseAppInitProps} from '../types';

export function useAppInit({apiClient, userIdProp = 'id'}: UseAppInitProps) {
    const {logout, user} = useAuth<Record<string, string> & AuthUser>();
    const {pushInstruction} = useMatomo();

    React.useEffect(() => {
        pushInstruction(
            'setUserId',
            user ? (user[userIdProp as keyof typeof user] as string) : ''
        );
        setSentryUser(user);
    }, [user]);

    const onError = useRequestErrorHandler({
        onError: toast,
        logout: redirectPathAfterLogin => {
            logout({
                redirectPathAfterLogin,
                quiet: true,
            });
        },
    });

    useEffect(() => {
        apiClient.addErrorListener(onError);

        return () => {
            apiClient.removeErrorListener(onError);
        };
    }, [onError]);
}
