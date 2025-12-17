import {useContext} from 'react';
import {Asset} from '../../types';
import {Box, Paper, Popper, Stack, useTheme} from '@mui/material';
import FilePlayer from '../Media/Asset/FilePlayer';
import {getRelativeViewHeight, getRelativeViewWidth} from '../../lib/style';
import Attributes, {
    attributesClasses,
    attributesSx,
} from '../Media/Asset/Attribute/Attributes';
import {DisplayContext} from '../Media/DisplayContext';
import {ZIndex} from '../../themes/zIndex.ts';
import {collectionListSx} from '../Media/Asset/Widgets/AssetCollectionList.tsx';
import IconButton from '@mui/material/IconButton';
import LockIcon from '@mui/icons-material/Lock';
import {getMediaBackgroundColor} from '../uiVars.ts';

type Props = {
    anchorEl: HTMLElement | undefined;
    asset: Asset | undefined;
    displayAttributes: boolean;
    zIndex: number | undefined;
    onHide: () => void;
};

export default function PreviewPopover({
    asset,
    onHide,
    anchorEl,
    displayAttributes,
    zIndex = ZIndex.assetPreview,
}: Props) {
    const {
        state: {previewLocked, previewOptions},
        setState,
        inOverflowDiv,
    } = useContext(DisplayContext)!;
    const relativeSize = previewOptions.sizeRatio;
    const width = getRelativeViewWidth(relativeSize);
    const height = getRelativeViewHeight(relativeSize);
    const previewRatio = 1 - previewOptions.attributesRatio / 100;
    const theme = useTheme();
    const padding = 1;
    const spacingInt = parseInt(theme.spacing(padding));

    const previewWidth = displayAttributes ? width * previewRatio : width;
    const attributeWidth = displayAttributes
        ? width * (1 - previewRatio)
        : width;

    enum Classes {
        Attributes = 'ppop-attrs',
        File = 'ppop-file',
    }

    return (
        <Popper
            keepMounted={true}
            open={Boolean(asset && anchorEl)}
            placement="bottom-start"
            anchorEl={anchorEl}
            sx={{
                pointerEvents: !previewLocked ? 'none' : undefined,
                zIndex,
                [`&:not(:has(.${Classes.File})):not(:has(.${attributesClasses.container}))`]:
                    {
                        display: 'none',
                    },
            }}
            modifiers={
                !inOverflowDiv
                    ? [
                          {
                              name: 'flip',
                              enabled: true,
                              options: {
                                  altBoundary: true,
                                  rootBoundary: 'viewport',
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
                                  rootBoundary: 'viewport',
                                  padding: 8,
                              },
                          },
                      ]
                    : undefined
            }
        >
            {asset ? (
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
                            position: 'relative',
                        }}
                    >
                        {previewOptions.displayFile && asset.preview && (
                            <div
                                className={Classes.File}
                                style={{
                                    display: 'flex',
                                    justifyContent: 'center',
                                    flexFlow: 'row nowrap',
                                    alignItems: 'center',
                                    backgroundColor:
                                        getMediaBackgroundColor(theme),
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
                        )}
                        {displayAttributes &&
                            previewOptions.displayAttributes && (
                                <Box
                                    className={Classes.Attributes}
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
                        {previewLocked ? (
                            <IconButton
                                sx={{
                                    position: 'absolute',
                                    top: 0,
                                    right: 0,
                                    zIndex: 1,
                                    color: theme.palette.error.main,
                                }}
                                onClick={() => {
                                    setState(p => ({
                                        ...p,
                                        previewLocked: false,
                                    }));
                                    onHide();
                                }}
                            >
                                <LockIcon fontSize={'small'} />
                            </IconButton>
                        ) : null}
                    </Stack>
                </Paper>
            ) : null}
        </Popper>
    );
}
