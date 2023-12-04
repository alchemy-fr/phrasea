import React, {useContext, useEffect} from 'react';
import AssetSelectionProvider from './Media/AssetSelectionProvider';
import MainAppBar, {menuHeight} from './Layout/MainAppBar';
import LeftPanel from './Media/LeftPanel';
import ResultProvider from './Media/Search/ResultProvider';
import AssetResults from './Media/Search/AssetResults';
import SearchProvider from './Media/Search/SearchProvider';
import AssetDropzone from './Media/Asset/AssetDropzone';
import {toast, ToastContainer} from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import {Box, Theme, useMediaQuery} from '@mui/material';
import axios, {AxiosError} from 'axios';
import {UserContext} from './Security/UserContext';
import {useTranslation} from 'react-i18next';
import apiClient from '../api/api-client';
import DisplayProvider from './Media/DisplayProvider';
import {Outlet, useLocation} from 'react-router-dom';
import uploaderClient from '../api/uploader-client';
import {zIndex} from '../themes/zIndex';
import AttributeFormatProvider from './Media/Asset/Attribute/Format/AttributeFormatProvider';
import {routes} from '../routes.ts';
import {MatomoRouteProxy, RouterProvider} from '@alchemy/navigation';

declare module 'axios' {
    export interface AxiosRequestConfig {
        anonymous?: boolean;
        errorHandled?: boolean;
    }
}

const AppProxy = React.memo(() => {
    const isSmallView = useMediaQuery((theme: Theme) =>
        theme.breakpoints.down('md')
    );
    const [leftPanelOpen, setLeftPanelOpen] = React.useState(!isSmallView);
    const toggleLeftPanel = React.useCallback(() => {
        setLeftPanelOpen(p => !p);
    }, []);

    useEffect(() => {
        setLeftPanelOpen(!isSmallView);
    }, [isSmallView]);

    return (
        <RouterProvider
            routes={routes}
            RouteProxyComponent={MatomoRouteProxy}
        />
    );

    return (
        <SearchProvider>
            <ResultProvider>
                <AssetDropzone>
                    <MainAppBar
                        leftPanelOpen={leftPanelOpen}
                        onToggleLeftPanel={toggleLeftPanel}
                    />
                    <AttributeFormatProvider>
                        <AssetSelectionProvider>
                            <DisplayProvider>
                                <div
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'row',
                                        height: `calc(100vh - ${menuHeight}px)`,
                                    }}
                                >
                                    {leftPanelOpen && (
                                        <Box
                                            sx={theme => ({
                                                width: 360,
                                                flexGrow: 0,
                                                flexShrink: 0,
                                                height: `calc(100vh - ${menuHeight}px)`,
                                                overflow: 'auto',
                                                boxShadow: theme.shadows[5],
                                                zIndex: zIndex.leftPanel,
                                            })}
                                        >
                                            <LeftPanel />
                                        </Box>
                                    )}
                                    <div
                                        style={{
                                            flexGrow: 1,
                                        }}
                                    >
                                        <AssetResults />
                                    </div>
                                </div>
                            </DisplayProvider>
                        </AssetSelectionProvider>
                    </AttributeFormatProvider>
                </AssetDropzone>
            </ResultProvider>
        </SearchProvider>
    );
});

export default function App() {
    const userContext = useContext(UserContext);
    const {t} = useTranslation();
    const location = useLocation();

    useEffect(() => {
        const onError = (error: AxiosError<any>) => {
            if (
                error.config?.errorHandled ||
                (axios.isCancel(error) as boolean)
            ) {
                return;
            }

            const status = error.response?.status;

            switch (status) {
                case 401:
                    toast.error(
                        t(
                            'error.session_expired',
                            'Your session has expired'
                        ) as string
                    );
                    userContext.logout && userContext.logout(false);
                    break;
                case 403:
                    toast.error(
                        t('error.http_unauthorized', 'Unauthorized') as string
                    );
                    break;
                case 400:
                    toast.error(
                        error.response?.data['hydra:description'] as
                            | string
                            | undefined
                    );
                    break;
                case 404:
                    toast.error(
                        error.response?.data['hydra:description'] as
                            | string
                            | undefined
                    );
                    break;
                case 422:
                    // Handled by form
                    break;
                default:
                    toast.error(
                        t('error.http_error', 'Server error') as string
                    );
                    break;
            }
        };
        apiClient.addErrorListener(onError);
        uploaderClient.addErrorListener(onError);

        return () => {
            apiClient.removeErrorListener(onError);
            uploaderClient.removeErrorListener(onError);
        };
    }, []);

    return (
        <>
            <ToastContainer />
            <Outlet />
            <AppProxy />
        </>
    );
}
