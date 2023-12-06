import React, {PropsWithChildren} from 'react';
import UploaderUserContext from "./UploaderUserContext";
import {useAuth} from '@alchemy/auth';
import apiClient from "../lib/apiClient";
import {UploaderUser} from "../types.ts";

type Props = PropsWithChildren<{}>;

export default function UploaderUserProvider({children}: Props) {
    const {tokens} = useAuth();
    const [uploaderUser, setUploaderUser] = React.useState<UploaderUser | undefined>();

    React.useEffect(() => {
        apiClient.get('/me').then(r => {
            setUploaderUser(r.data);
        })
    }, [tokens]);

    return <UploaderUserContext.Provider value={{
        uploaderUser,
    }}>
        {children}
    </UploaderUserContext.Provider>
}
