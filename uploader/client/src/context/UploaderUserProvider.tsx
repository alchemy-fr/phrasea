import React, {PropsWithChildren} from 'react';
import UploaderUserContext from './UploaderUserContext';
import {useAuth} from '@alchemy/react-auth';
import {UploaderUser} from '../types.ts';
import {apiClient} from '../init.ts';

type Props = PropsWithChildren<{}>;

export default function UploaderUserProvider({children}: Props) {
    const {user} = useAuth();
    const [uploaderUser, setUploaderUser] = React.useState<
        UploaderUser | undefined
    >();

    React.useEffect(() => {
        if (user) {
            apiClient.get('/me').then(r => {
                setUploaderUser(r.data);
            });
        }
    }, [user?.id]);

    return (
        <UploaderUserContext.Provider
            value={{
                uploaderUser,
            }}
        >
            {children}
        </UploaderUserContext.Provider>
    );
}
