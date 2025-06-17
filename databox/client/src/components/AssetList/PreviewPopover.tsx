import {useContext} from 'react';
import {Asset} from '../../types';
import {Box, Paper, Popper, Stack, useTheme} from '@mui/material';
import FilePlayer from '../Media/Asset/FilePlayer';
import {getRelativeViewHeight, getRelativeViewWidth} from '../../lib/style';
import Attributes, {attributesSx} from '../Media/Asset/Attribute/Attributes';
import {DisplayContext} from '../Media/DisplayContext';
import {ZIndex} from '../../themes/zIndex.ts';
import {getMediaBackgroundColor} from '../../themes/base.ts';
import {collectionListSx} from '../Media/Asset/Widgets/AssetCollectionList.tsx';

type Props = {
    anchorEl: HTMLElement | undefined;
    asset: Asset | undefined;
    displayAttributes: boolean;
    zIndex: number | undefined;
};

export default function PreviewPopover({
    asset,
    anchorEl,
    displayAttributes,
    zIndex = ZIndex.assetPreview,
}: Props) {
    const relativeSize = 70;
    const previewRatio = 0.7;
    const width = getRelativeViewWidth(relativeSize);
    const height = getRelativeViewHeight(relativeSize);
    const {previewLocked} = useContext(DisplayContext)!;
    const theme = useTheme();
    const padding = 1;
    const spacingInt = parseInt(theme.spacing(padding));

    const previewWidth = displayAttributes ? width * previewRatio : width;
    const attributeWidth = displayAttributes
        ? width * (1 - previewRatio)
        : width;

    return (
        <Popper
            keepMounted={true}
            open={Boolean(asset && anchorEl)}
            placement="bottom"
            anchorEl={anchorEl}
            sx={{
                pointerEvents: !previewLocked ? 'none' : undefined,
                zIndex,
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
            {asset && (
                <Paper
                    elevation={6}
                    sx={{
                        padding,
                        maxWidth: width,
                        maxHeight: height,
                        ...attributesSx(),
                        ...collectionListSx(),
                    }}
                >
                    <Stack
                        direction={'row'}
                        style={{
                            maxHeight: height - spacingInt,
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'center',
                                flexFlow: 'row nowrap',
                                alignItems: 'center',
                                backgroundColor: getMediaBackgroundColor(theme),
                                width: previewWidth,
                            }}
                        >
                            <FilePlayer
                                key={asset.id}
                                file={asset.preview!.file!}
                                dimensions={{
                                    width: previewWidth,
                                    height: height - spacingInt * 2,
                                }}
                                title={asset.resolvedTitle}
                                noInteraction={!previewLocked}
                                controls={previewLocked}
                                autoPlayable={true}
                            />
                        </div>
                        {displayAttributes && (
                            <Box
                                sx={{
                                    'width': attributeWidth,
                                    'maxHeight': height - spacingInt * 2,
                                    'overflowY': previewLocked
                                        ? 'auto'
                                        : 'clip',
                                    'overflowX': 'visible',
                                    'overflowClipMargin': theme.spacing(1),
                                    'paddingLeft': theme.spacing(2),
                                    '&:empty': {
                                        display: 'none',
                                    },
                                }}
                            >
                                <Attributes
                                    asset={asset}
                                    displayControls={previewLocked}
                                    pinnedOnly={true}
                                />
                            </Box>
                        )}
                    </Stack>
                </Paper>
            )}
        </Popper>
    );
}
