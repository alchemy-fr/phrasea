import React, {useEffect, useRef} from 'react';
import ResultProvider from './Media/Search/ResultProvider';
import SearchProvider from './Media/Search/SearchProvider';
import AssetDropzone from './Media/Asset/AssetDropzone';
import {ToastContainer} from 'react-toastify';
import {Theme, useMediaQuery} from '@mui/material';
import DisplayProvider from './Media/DisplayProvider';
import {useLocation} from '@alchemy/navigation';
import {setSentryUser} from '@alchemy/core';
import {useAuth} from '@alchemy/react-auth';
import AssetSearch from './AssetSearch/AssetSearch';
import PendingUploads from './Upload/PendingUploads.tsx';
import AppLayout from './Layout/AppLayout.tsx';

function isDrawer(locationSearch: string): boolean {
    return locationSearch.includes('_m=');
}

const AppProxy = React.memo(
    ({locationSearch}: {locationSearch: string}) => {
        const alreadyRendered = useRef(false);
        const isSmallScreen = useMediaQuery((theme: Theme) =>
            theme.breakpoints.down('md')
        );

        const [leftPanelOpen, setLeftPanelOpen] =
            React.useState(!isSmallScreen);
        const toggleLeftPanel = React.useCallback(() => {
            setLeftPanelOpen(p => !p);
        }, []);

        useEffect(() => {
            setLeftPanelOpen(!isSmallScreen);
        }, [isSmallScreen]);

        if (isDrawer(locationSearch) && !alreadyRendered.current) {
            return null;
        }

        alreadyRendered.current = true;

        return (
            <>
                <style>{'body { overflow: hidden; }'}</style>
                <PendingUploads />
                <SearchProvider>
                    <ResultProvider>
                        <AssetDropzone>
                            <AppLayout
                                leftPanelOpen={leftPanelOpen}
                                toggleLeftPanel={toggleLeftPanel}
                            >
                                <DisplayProvider>
                                    <AssetSearch />
                                </DisplayProvider>
                            </AppLayout>
                        </AssetDropzone>
                    </ResultProvider>
                </SearchProvider>
            </>
        );
    },
    (a, b) => {
        const dA = isDrawer(a.locationSearch);
        const dB = isDrawer(b.locationSearch);

        return dA === dB || (!dA && dB);
    }
);

export default function App() {
    const {user} = useAuth();
    const location = useLocation();

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
