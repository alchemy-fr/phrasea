import React, {useCallback, useState} from 'react';
import {Asset} from "../../../types";
import {Paper, Popper} from "@mui/material";
import FilePlayer from "./FilePlayer";
import {getRelativeViewHeight, getRelativeViewWidth} from "../../../lib/style";
import {createDimensions} from "./Players";
import {zIndex} from "../../../themes/zIndex";

type Props = {
    anchorEl: HTMLElement | undefined;
    asset: Asset | undefined;
    previewLocked: boolean;
};

const relativeSize = 50;

export default function PreviewPopover({
                                           previewLocked,
                                           asset,
                                           anchorEl,
                                       }: Props) {
    const [anchor, setAnchor] = useState<HTMLElement>();
    const size = Math.min(getRelativeViewWidth(relativeSize), getRelativeViewHeight(relativeSize));

    const onLoad = useCallback(() => {
        setAnchor(anchorEl);
    }, [anchorEl]);

    return <Popper
        keepMounted={true}
        open={Boolean(anchor && asset)}
        placement="bottom"
        anchorEl={anchor || null}
        sx={{
            pointerEvents: 'none',
            zIndex: zIndex.assetPreview,
        }}
        modifiers={[
            {
                name: 'flip',
                enabled: true,
                options: {
                    altBoundary: true,
                    rootBoundary: 'document',
                    padding: 8,
                },
            },
            {
                name: 'preventOverflow',
                enabled: true,
                options: {
                    altAxis: true,
                    altBoundary: true,
                    tether: true,
                    rootBoundary: 'document',
                    padding: 8,
                },
            },
        ]}
    >
        {asset && <Paper
            elevation={6}
            sx={{
                padding: 1,
            }}
        >
            <FilePlayer
                key={asset.id}
                file={asset.preview!.file!}
                maxDimensions={createDimensions(size)}
                title={asset.resolvedTitle}
                onLoad={onLoad}
                noInteraction={!previewLocked}
                controls={previewLocked}
                autoPlayable={true}
            />
        </Paper>}
    </Popper>
}
