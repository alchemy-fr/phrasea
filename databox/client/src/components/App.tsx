import React, {useEffect} from 'react';
import AssetSelectionProvider from './Media/AssetSelectionProvider';
import MainAppBar, {menuHeight} from './Layout/MainAppBar';
import LeftPanel from './Media/LeftPanel';
import ResultProvider from './Media/Search/ResultProvider';
import SearchProvider from './Media/Search/SearchProvider';
import AssetDropzone from './Media/Asset/AssetDropzone';
import {ToastContainer} from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import {Box, Theme, useMediaQuery} from '@mui/material';
import apiClient from '../api/api-client';
import DisplayProvider from './Media/DisplayProvider';
import uploaderClient from '../api/uploader-client';
import {zIndex} from '../themes/zIndex';
import AttributeFormatProvider from './Media/Asset/Attribute/Format/AttributeFormatProvider';
import {useRequestErrorHandler} from '@alchemy/api';
import {setSentryUser} from '@alchemy/core';
import {useAuth} from '@alchemy/react-auth';
import AssetList from "./AssetList/AssetList.tsx";

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
                                            <LeftPanel/>
                                        </Box>
                                    )}
                                    <div
                                        style={{
                                            flexGrow: 1,
                                        }}
                                    >
                                        <AssetList
                                            pages={[]}
                                            loading={false}
                                        />
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
    const {logout, user} = useAuth();
    const onError = useRequestErrorHandler({
        logout: redirectPathAfterLogin => {
            logout({
                redirectPathAfterLogin,
                quiet: true,
            });
        },
    });

    React.useEffect(() => {
        apiClient.addErrorListener(onError);
        uploaderClient.addErrorListener(onError);

        return () => {
            apiClient.removeErrorListener(onError);
            uploaderClient.removeErrorListener(onError);
        };
    }, [onError]);

    React.useEffect(() => {
        setSentryUser(user);
    }, [user]);

    return (
        <>
            <ToastContainer/>
            <AppProxy/>
        </>
    );
}
