import React, {useEffect, useRef} from 'react';
import MainAppBar, {menuHeight} from './Layout/MainAppBar';
import LeftPanel from './Media/LeftPanel';
import ResultProvider from './Media/Search/ResultProvider';
import SearchProvider from './Media/Search/SearchProvider';
import AssetDropzone from './Media/Asset/AssetDropzone';
import {toast, ToastContainer} from 'react-toastify';
import {Box, Theme, useMediaQuery} from '@mui/material';
import apiClient from '../api/api-client';
import DisplayProvider from './Media/DisplayProvider';
import uploaderClient from '../api/uploader-client';
import {ZIndex} from '../themes/zIndex';
import {useRequestErrorHandler} from '@alchemy/api';
import {useLocation} from '@alchemy/navigation';
import {setSentryUser} from '@alchemy/core';
import {useAuth} from '@alchemy/react-auth';
import AssetSearch from './AssetSearch/AssetSearch';
import {leftPanelWidth} from '../themes/base';

function isDrawer(locationSearch: string): boolean {
    return locationSearch.includes('_m=');
}

const AppProxy = React.memo(
    ({locationSearch}: {locationSearch: string}) => {
        const alreadyRendered = useRef(false);
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

        if (isDrawer(locationSearch) && !alreadyRendered.current) {
            return null;
        }

        alreadyRendered.current = true;

        return (
            <SearchProvider>
                <ResultProvider>
                    <AssetDropzone>
                        <MainAppBar
                            leftPanelOpen={leftPanelOpen}
                            onToggleLeftPanel={toggleLeftPanel}
                        />
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
                                            width: leftPanelWidth,
                                            flexGrow: 0,
                                            flexShrink: 0,
                                            height: `calc(100vh - ${menuHeight}px)`,
                                            overflow: 'auto',
                                            boxShadow: theme.shadows[5],
                                            zIndex: ZIndex.leftPanel,
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
                                    <AssetSearch />
                                </div>
                            </div>
                        </DisplayProvider>
                    </AssetDropzone>
                </ResultProvider>
            </SearchProvider>
        );
    },
    (a, b) => {
        const dA = isDrawer(a.locationSearch);
        const dB = isDrawer(b.locationSearch);

        return dA === dB || (!dA && dB);
    }
);

export default function App() {
    const {logout, user} = useAuth();
    const location = useLocation();
    const onError = useRequestErrorHandler({
        onError: toast,
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
            <ToastContainer position={'bottom-left'} stacked />
            <AppProxy locationSearch={location.search} />
        </>
    );
}
