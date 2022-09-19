import React, {FC, useCallback, useEffect, useMemo, useState} from 'react';
import {Asset} from "../../../types";
import AppDialog from "../../Layout/AppDialog";
import FilePlayer from "./FilePlayer";
import useWindowSize from "../../../hooks/useWindowSize";
import {Dimensions} from "./Players";
import {Box} from "@mui/material";
import FileIntegrations from "./FileIntegrations";
import {useParams} from "react-router-dom";
import {getAsset} from "../../../api/asset";
import FullPageLoader from "../../Ui/FullPageLoader";
import RouteDialog from "../../Dialog/RouteDialog";

export type IntegrationOverlayCommonProps = {
    maxDimensions: Dimensions;
}

type IntegrationOverlay<P extends {} = any> = {
    component: FC<P>;
    props: P;
    replace: boolean;
}

export type SetIntegrationOverlayFunction<P extends {} = any> = (component: FC<P & IntegrationOverlayCommonProps>, props?: P, replace?: boolean) => void;

const menuWidth = 300;

const headerHeight = 60;
const scrollBarDelta = 8;

type Props = {};

export default function AssetView({}: Props) {
    const {id} = useParams();

    const [data, setData] = useState<Asset>();

    useEffect(() => {
        getAsset(id!).then(c => setData(c));
    }, [id]);


    const winSize = useWindowSize();
    const [integrationOverlay, setIntegrationOverlay] = useState<IntegrationOverlay>();

    const setProxy: SetIntegrationOverlayFunction = useCallback((component, props, replace = false) => {
        setIntegrationOverlay({
            component,
            props,
            replace,
        });
    }, [setIntegrationOverlay]);

    const maxDimensions = useMemo<Dimensions>(() => {
        return {
            width: winSize.width - menuWidth - scrollBarDelta,
            height: winSize.height - headerHeight - 2,
        };
    }, [winSize]);

    if (!data) {
        return <FullPageLoader/>
    }

    const file = data.original;

    return <RouteDialog>
        {({open, onClose}) => <AppDialog
            open={open}
            disablePadding={true}
            sx={{
                '.MuiDialogTitle-root': {
                    height: headerHeight,
                    maxHeight: headerHeight,
                }
            }}
            fullScreen={true}
            title={<>
                Edit asset{' '}
                <b>
                    {data.resolvedTitle}
                </b>
            </>}
            onClose={onClose}
        >
            <Box sx={{
                height: maxDimensions.height,
                display: 'flex',
                flexDirection: 'row',
                justifyContent: 'space-between'
            }}>
                <Box sx={{
                    overflowY: 'auto',
                    height: maxDimensions.height,
                    width: maxDimensions.width + scrollBarDelta,
                    maxWidth: maxDimensions.width + scrollBarDelta,
                }}>
                    <div style={{
                        position: 'relative',
                        width: 'fit-content'
                    }}>
                        {file && (!integrationOverlay || !integrationOverlay.replace) && <FilePlayer
                            file={file}
                            title={data.title}
                            maxDimensions={maxDimensions}
                            autoPlayable={false}
                        />}
                        {integrationOverlay && React.createElement(integrationOverlay.component, {
                            maxDimensions,
                            ...(integrationOverlay.props || {})
                        })}
                    </div>
                </Box>
                <Box
                    sx={theme => ({
                        width: menuWidth,
                        maxWidth: menuWidth,
                        borderLeft: `1px solid ${theme.palette.divider}`,
                        overflowY: 'auto',
                        height: maxDimensions.height,
                    })}
                >
                    {data?.original && <FileIntegrations
                        file={data.original}
                        setIntegrationOverlay={setProxy}
                    />}
                </Box>
            </Box>
        </AppDialog>}
    </RouteDialog>
}
