import React, {PureComponent, Suspense} from 'react';
import {oauthClient} from "../oauth";
import config from "../config";
import apiClient from "../api/api-client";
import {User} from "../types";
import {UserContext} from "./Security/UserContext";
import FullPageLoader from "./Ui/FullPageLoader";
import Routes from "./Routing/Routes";
import {BrowserRouter} from "react-router-dom";
import ModalStack from "../hooks/useModalStack";
import UserPreferencesProvider from "./User/Preferences/UserPreferencesProvider";

type State = {
    user?: User; authenticating: boolean;
};

function authenticate() {
    return oauthClient.authenticate();
}

export default class Root extends PureComponent<{}, State> {
    state: State = {
        authenticating: oauthClient.hasAccessToken(),
    }

    componentDidMount() {
        oauthClient.registerListener('authentication', async (evt) => {
            apiClient.defaults.headers.common['Authorization'] = `Bearer ${oauthClient.getAccessToken()!}`;
            this.setState({
                user: (evt as unknown as {
                    user: User;
                }).user, authenticating: false,
            });
        });
        oauthClient.registerListener('login', async () => {
            await authenticate();
        });

        oauthClient.registerListener('logout', async () => {
            sessionStorage.clear();
            if (config.isDirectLoginForm()) {
                this.setState({
                    user: undefined,
                });
            } else {
                document.location.reload();
            }
        });

        this.authenticate();
    }

    public logout = () => {
        oauthClient.logout();
        if (!config.isDirectLoginForm()) {
            document.location.href = `${config.getAuthBaseUrl()}/security/logout?r=${encodeURIComponent(document.location.origin)}`;
        }
    }

    render() {
        return <UserContext.Provider value={{
            user: this.state.user,
            logout: this.state.user ? this.logout : undefined,
        }}>
            <UserPreferencesProvider>
                <ModalStack>
                    {this.state.authenticating ? <FullPageLoader/> : <Suspense fallback={`Loading...`}>
                        <BrowserRouter>
                            <Routes/>
                        </BrowserRouter>
                    </Suspense>}
                </ModalStack>
            </UserPreferencesProvider>
        </UserContext.Provider>
    }

    private authenticate = (): void => {
        if (!oauthClient.hasAccessToken()) {
            return;
        }

        this.setState({authenticating: true}, () => {
            authenticate().then(() => {
                this.setState({authenticating: false});
            }, (error: any) => {
                console.error(error);
                oauthClient.logout();
            });
        });
    }
}
