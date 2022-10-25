import React, {PureComponent, Suspense} from 'react';
import {oauthClient} from "../oauth";
import config from "../config";
import apiClient from "../api/api-client";
import {User} from "../types";
import {UserContext} from "./Security/UserContext";
import {CssBaseline, GlobalStyles, ThemeProvider} from "@mui/material";
import FullPageLoader from "./Ui/FullPageLoader";
import {createCachedTheme, ThemeName} from "../lib/theme";
import Routes from "./Routing/Routes";
import {BrowserRouter} from "react-router-dom";
import ModalStack from "../hooks/useModalStack";

type State = {
    user?: User;
    authenticating: boolean;
    theme: ThemeName;
};

const scrollbarWidth = 8;

function authenticate() {
    return oauthClient.authenticate();
}

export default class Root extends PureComponent<{}, State> {
    state: State = {
        authenticating: oauthClient.hasAccessToken(),
        theme: 'default',
    }

    componentDidMount() {
        oauthClient.registerListener('authentication', async (evt) => {
            apiClient.defaults.headers.common['Authorization'] = `Bearer ${oauthClient.getAccessToken()!}`;
            this.setState({
                user: (evt as unknown as {
                    user: User;
                }).user,
                authenticating: false,
            });
        });
        oauthClient.registerListener('login', async () => {
            await authenticate();
        });

        oauthClient.registerListener('logout', async () => {
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

    render() {
        return <ThemeProvider theme={createCachedTheme(this.state.theme)}>
            <CssBaseline/>
            <GlobalStyles
                styles={(theme) => ({
                    '*': {
                        '*::-webkit-scrollbar': {
                            width: scrollbarWidth
                        },
                        '*::-webkit-scrollbar-track': {
                            borderRadius: 10,
                        },
                        '*::-webkit-scrollbar-thumb': {
                            borderRadius: scrollbarWidth,
                            backgroundColor: theme.palette.primary.main,
                        }
                    },
                    body: {
                        backgroundColor: theme.palette.common.white,
                    }
                })}
            />
            <UserContext.Provider value={{
                user: this.state.user,
                logout: this.state.user ? this.logout : undefined,
                currentTheme: this.state.theme,
                changeTheme: (theme: ThemeName) => {
                    this.setState({theme});
                },
            }}>
                <ModalStack>
                    {this.state.authenticating
                        ? <FullPageLoader/>
                        : <Suspense fallback={`Loading...`}>
                            <BrowserRouter>
                                <Routes/>
                            </BrowserRouter>
                        </Suspense>}
                </ModalStack>
            </UserContext.Provider>
        </ThemeProvider>
    }
}
