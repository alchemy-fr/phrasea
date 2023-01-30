import React, {useCallback, useState} from 'react';
import {Asset} from "../../../types";
import {Paper, Popper, Stack} from "@mui/material";
import FilePlayer from "./FilePlayer";
import {getRelativeViewHeight, getRelativeViewWidth} from "../../../lib/style";
import {createDimensions} from "./Players";
import {zIndex} from "../../../themes/zIndex";
import Attributes from "./Attribute/Attributes";
import AttributeRowUI from "./Attribute/AttributeRowUI";
import {AttributeType} from "../../../api/attributes";

type Props = {
    anchorEl: HTMLElement | undefined;
    asset: Asset | undefined;
    previewLocked: boolean;
    displayAttributes: boolean;
};

const relativeSize = 50;

export default function PreviewPopover({
                                           previewLocked,
                                           asset,
                                           anchorEl,
    displayAttributes,
                                       }: Props) {
    const [anchor, setAnchor] = useState<HTMLElement>();
    const width = getRelativeViewWidth(relativeSize);
    const height = getRelativeViewHeight(relativeSize);

    const onLoad = useCallback(() => {
        setAnchor(anchorEl);
    }, [anchorEl]);

    return <Popper
        keepMounted={true}
        open={Boolean(anchor && asset)}
        placement="bottom"
        anchorEl={anchor || null}
        sx={{
            pointerEvents: !previewLocked ? 'none' : undefined,
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
                maxWidth: width,
            }}
        >
            <Stack
                direction={'row'}
                spacing={1}
            >
                <FilePlayer
                    key={asset.id}
                    file={asset.preview!.file!}
                    maxDimensions={createDimensions(width / 2, height)}
                    title={asset.resolvedTitle}
                    onLoad={onLoad}
                    noInteraction={!previewLocked}
                    controls={previewLocked}
                    autoPlayable={true}
                />
                {displayAttributes && <div
                    style={{
                        maxHeight: height,
                    }}
                >
                    <Attributes
                        asset={asset}
                        controls={previewLocked}
                    />
                </div>}
            </Stack>
        </Paper>}
    </Popper>
}
