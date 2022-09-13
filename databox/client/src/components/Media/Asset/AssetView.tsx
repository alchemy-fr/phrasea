import React, {FC, useCallback, useMemo, useState} from 'react';
import {Asset} from "../../../types";
import AppDialog from "../../Layout/AppDialog";
import {StackedModalProps} from "../../../hooks/useModalStack";
import {useModalHash} from "../../../hooks/useModalHash";
import FilePlayer from "./FilePlayer";
import useWindowSize from "../../../hooks/useWindowSize";
import {Dimensions} from "./Players";
import {Box} from "@mui/material";
import AssetIntegrationActions from "./AssetIntegrationActions";

type Props = {
    asset: Asset;
} & StackedModalProps;

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

export default function AssetView({
                                      asset,
                                      open,
                                  }: Props) {
    const {closeModal} = useModalHash();

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

    const file = asset.original;

    return <AppDialog
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
                {asset.resolvedTitle}
            </b>
        </>}
        onClose={closeModal}
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
                        title={asset.title}
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
                <AssetIntegrationActions
                    asset={asset}
                    setIntegrationOverlay={setProxy}
                />
            </Box>
        </Box>
    </AppDialog>
}
