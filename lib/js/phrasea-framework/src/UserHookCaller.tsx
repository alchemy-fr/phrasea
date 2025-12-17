import React from 'react';
import {useAuth} from '@alchemy/react-auth';
import {AuthUser} from '@alchemy/auth';
import {setSentryUser} from '@alchemy/core';
import {useMatomo} from '@jonkoops/matomo-tracker-react';

type Props = {
    idProp?: string;
};

export default function UserHookCaller({idProp = 'sub'}: Props) {
    const {user} = useAuth<Record<string, string> & AuthUser>();
    const {pushInstruction} = useMatomo();

    React.useEffect(() => {
        pushInstruction('setUserId', user ? (user[idProp] as string) : null);
        setSentryUser(user);
    }, [user]);

    return null;
}
