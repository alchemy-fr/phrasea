import React, {FC, useCallback, useEffect, useMemo, useState} from 'react';
import {Asset, AssetRendition} from "../../../types";
import AppDialog from "../../Layout/AppDialog";
import FilePlayer from "./FilePlayer";
import useWindowSize from "../../../hooks/useWindowSize";
import {Dimensions} from "./Players";
import {Box, Select} from "@mui/material";
import FileIntegrations from "./FileIntegrations";
import {useNavigate, useParams} from "react-router-dom";
import {getAsset} from "../../../api/asset";
import FullPageLoader from "../../Ui/FullPageLoader";
import RouteDialog from "../../Dialog/RouteDialog";
import {getAssetRenditions} from "../../../api/rendition";
import MenuItem from "@mui/material/MenuItem";
import {getPath} from "../../../routes";

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
    const {assetId, renditionId} = useParams();
    const navigate = useNavigate();

    const [data, setData] = useState<Asset>();
    const [renditions, setRenditions] = useState<AssetRendition[]>();

    useEffect(() => {
        getAsset(assetId!).then(c => setData(c));
        getAssetRenditions(assetId!).then(r => setRenditions(r.result));
    }, [assetId]);

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

    if (!data || !renditions) {
        return <FullPageLoader/>
    }

    const rendition = renditions.find(r => r.id === renditionId);

    const handleRenditionChange = (renditionId: string) => {
        navigate(getPath('app_asset_view', {
            assetId,
            renditionId,
        }));
    }

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

                <Select<string>
                    label={''}
                    value={rendition?.id}
                    onChange={(e) => handleRenditionChange(e.target.value)}
                >
                    {renditions.map((r: AssetRendition) => <MenuItem
                        key={r.id}
                        value={r.id}
                    >
                        {r.name}
                    </MenuItem>)}
                </Select>
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
                        {rendition?.file && (!integrationOverlay || !integrationOverlay.replace) && <FilePlayer
                            file={rendition.file}
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
                    {rendition?.file && <FileIntegrations
                        key={rendition.file.id}
                        asset={data}
                        file={rendition.file}
                        setIntegrationOverlay={setProxy}
                    />}
                </Box>
            </Box>
        </AppDialog>}
    </RouteDialog>
}
