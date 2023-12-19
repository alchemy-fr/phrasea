import React from 'react';
import {useUser} from "../hooks/useUser";
import {useMatomo} from "@jonkoops/matomo-tracker-react";

type Props = {
    idProp?: string;
};

export default function MatomoUser({
    idProp = 'sub'
}: Props) {
    const {user} = useUser<Record<string, string>>();
    const {pushInstruction} = useMatomo();

    React.useEffect(() => {
        pushInstruction('setUserId', user ? (user[idProp] as string) : null);
    }, [user]);

    return null
}
