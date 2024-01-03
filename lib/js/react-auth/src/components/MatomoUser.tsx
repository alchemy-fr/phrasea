import React from 'react';
import {useAuth} from "../hooks/useAuth";
import {AuthUser} from '@alchemy/auth'
import {useMatomo} from "@jonkoops/matomo-tracker-react";

type Props = {
    idProp?: string;
};

export default function MatomoUser({
    idProp = 'sub'
}: Props) {
    const {user} = useAuth<Record<string, string> & AuthUser>();
    const {pushInstruction} = useMatomo();

    React.useEffect(() => {
        pushInstruction('setUserId', user ? (user[idProp] as string) : null);
    }, [user]);

    return null
}
