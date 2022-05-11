import React, {PureComponent, Suspense} from 'react';
import {BrowserRouter, Routes} from "react-router-dom";
import {oauthClient} from "../oauth";
import {authenticate} from "../auth";
import config from "../config";
import apiClient from "../api/api-client";
import {User} from "../types";
import {UserContext} from "./Security/UserContext";
import {createTheme, CssBaseline, GlobalStyles, ThemeProvider} from "@mui/material";
import {flattenRoutes, RouteDefinition} from "../routes";
import createRoute from "./Router/router";
import {ModalStack} from "@mattjennings/react-modal-stack";
import FullPageLoader from "./Ui/FullPageLoader";

type State = {
    user?: User,
    authenticating: boolean,
};

const theme = createTheme({
    palette: {
        primary: {
            main: '#1f4b80',
        },
        secondary: {
            main: '#fa7515',
        },
    }
});

const scrollbarWidth = 8;

export default class Root extends PureComponent<{}, State> {
    state: State = {
        authenticating: oauthClient.hasAccessToken(),
    }

    componentDidMount() {
        oauthClient.registerListener('authentication', (evt: { user: User }) => {
            apiClient.defaults.headers.common['Authorization'] = `Bearer ${oauthClient.getAccessToken()}`;
            this.setState({
                user: evt.user,
                authenticating: false,
            });
        });
        oauthClient.registerListener('login', authenticate);

        oauthClient.registerListener('logout', () => {
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
        return <ThemeProvider theme={theme}>
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
                    }
                })}
            />
            <ModalStack>
                <UserContext.Provider value={{
                    user: this.state.user,
                    logout: this.state.user ? this.logout : undefined,
                }}>
                    {this.state.authenticating
                        ? <FullPageLoader/>
                        : <Suspense fallback={`Loading...`}>
                            <BrowserRouter>
                                <Routes>
                                    {flattenRoutes.map((route: RouteDefinition, index: number) => createRoute(route, index.toString()))}
                                </Routes>
                            </BrowserRouter>
                        </Suspense>}
                </UserContext.Provider>
            </ModalStack>
        </ThemeProvider>
    }
}
