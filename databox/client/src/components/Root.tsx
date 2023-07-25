import React, {Suspense} from 'react';
import {User} from "../types";
import {UserContext} from "./Security/UserContext";
import Routes from "./Routing/Routes";
import {BrowserRouter} from "react-router-dom";
import ModalStack from "../hooks/useModalStack";
import UserPreferencesProvider from "./User/Preferences/UserPreferencesProvider";
import {oauthClient} from "../api/api-client";

type Props = {};

export default function Root({}: Props) {
    const [user, setUser] = React.useState<User | undefined>();

    React.useEffect(() => {
        const onLogin = async () => {
            const userInfo = oauthClient.getDecodedToken()!;

            setUser({
                id: userInfo.sub,
                roles: userInfo.roles,
                groups: userInfo.groups,
                username: userInfo.preferred_username,
            });
        };

        const onLogout = async () => {
            setUser(undefined);
        };

        oauthClient.registerListener('login', onLogin);
        oauthClient.registerListener('logout', onLogout);

        if (oauthClient.isAuthenticated()) {
            onLogin();
        } else {
            onLogout();
        }

        return () => {
            oauthClient.unregisterListener('login', onLogin);
            oauthClient.unregisterListener('logout', onLogout);
        }
    }, [setUser]);

    const logout = React.useCallback(() => {
        oauthClient.logout();
    }, []);

    return <UserContext.Provider value={{
        user,
        logout,
    }}>
        <UserPreferencesProvider>
            <ModalStack>
                <Suspense fallback={`Loading...`}>
                    <BrowserRouter>
                        <Routes/>
                    </BrowserRouter>
                </Suspense>
            </ModalStack>
        </UserPreferencesProvider>
    </UserContext.Provider>
}
