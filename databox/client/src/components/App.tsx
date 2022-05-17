import React, {useContext, useEffect} from 'react';
import AssetSelectionProvider from "./Media/AssetSelectionProvider";
import MainAppBar, {menuHeight} from "./Layout/MainAppBar";
import LeftPanel from "./Media/LeftPanel";
import ResultProvider from "./Media/Search/ResultProvider";
import AssetResults from "./Media/Search/AssetResults";
import SearchProvider from "./Media/Search/SearchProvider";
import AssetDropzone from "./Media/Asset/AssetDropzone";
import {toast, ToastContainer} from "react-toastify";
import 'react-toastify/dist/ReactToastify.css';
import {Box} from "@mui/material";
import {AxiosError} from "axios";
import {UserContext} from "./Security/UserContext";
import {useTranslation} from "react-i18next";
import {addErrorListener, removeErrorListener} from "../api/api-client";
import DisplayProvider from "./Media/DisplayProvider";

export default function App() {
    const userContext = useContext(UserContext);
    const {t} = useTranslation();

    useEffect(() => {
        const onError = (error: AxiosError<any>) => {
            if (error.config?.errorHandled) {
                return;
            }

            const status = error.response?.status;
            switch (status) {
                case 401:
                    toast.error(t('error.session_expired', 'Your session has expired'));
                    userContext.logout && userContext.logout();
                    break;
                case 403:
                    toast.error(t('error.http_unauthorized', 'Unauthorized'));
                    break;
                case 400:
                    toast.error(error.response?.data['hydra:description']);
                    break;
                case 422:
                    // Handled by form
                    break;
                default:
                    toast.error(t('error.http_error', 'Server error'));
                    break;

            }
        }
        addErrorListener(onError);

        return () => {
            removeErrorListener(onError);
        }
    }, [])


    return <>
        <ToastContainer/>
        <SearchProvider>
            <ResultProvider>
                <AssetDropzone>
                    <MainAppBar/>
                    <AssetSelectionProvider>
                        <DisplayProvider>
                            <Box style={{
                                display: 'flex',
                                flexDirection: 'row',
                                height: `calc(100vh - ${menuHeight}px)`,
                            }}>
                                <Box sx={(theme) => ({
                                    width: 360,
                                    flexGrow: 0,
                                    flexShrink: 0,
                                    height: `calc(100vh - ${menuHeight}px)`,
                                    overflow: 'auto',
                                    boxShadow: theme.shadows[5],
                                })}>
                                    <LeftPanel/>
                                </Box>
                                <Box sx={{
                                    flexGrow: 1,
                                }}>
                                    <AssetResults/>
                                </Box>
                            </Box>
                        </DisplayProvider>
                    </AssetSelectionProvider>
                </AssetDropzone>
            </ResultProvider>
        </SearchProvider>
    </>
}
