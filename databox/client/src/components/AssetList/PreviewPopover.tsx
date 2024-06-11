import {useCallback, useContext, useState} from 'react';
import {Asset} from '../../types';
import {Paper, Popper, Stack} from '@mui/material';
import FilePlayer from '../Media/Asset/FilePlayer';
import {getRelativeViewHeight, getRelativeViewWidth} from '../../lib/style';
import Attributes, {attributesSx} from '../Media/Asset/Attribute/Attributes';
import {DisplayContext} from '../Media/DisplayContext';
import {ZIndex} from "../../themes/zIndex.ts";

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
    const relativeSize = 50;
    const [anchor, setAnchor] = useState<HTMLElement>();
    const width = getRelativeViewWidth(relativeSize);
    const height = getRelativeViewHeight(relativeSize);
    const {previewLocked} = useContext(DisplayContext)!;

    const onLoad = useCallback(() => {
        setAnchor(anchorEl);
    }, [anchorEl]);

    return (
        <Popper
            keepMounted={true}
            open={Boolean(anchor && asset)}
            placement="bottom"
            anchorEl={anchor || null}
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
                        padding: 1,
                        maxWidth: width,
                        maxHeight: height,
                        ...attributesSx(),
                    }}
                >
                    <Stack
                        direction={'row'}
                        spacing={1}
                        sx={theme => ({
                            maxHeight: `calc(${height}px - ${theme.spacing(
                                2
                            )})`,
                        })}
                    >
                        <div
                            style={{
                                maxHeight: height,
                            }}
                        >
                            <FilePlayer
                                key={asset.id}
                                file={asset.preview!.file!}
                                dimensions={{
                                    width: width / 2,
                                    height,
                                }}
                                title={asset.resolvedTitle}
                                onLoad={onLoad}
                                noInteraction={!previewLocked}
                                controls={previewLocked}
                                autoPlayable={true}
                            />
                        </div>
                        {displayAttributes && (
                            <div
                                style={{
                                    maxHeight: height,
                                    overflow: previewLocked ? 'auto' : 'hidden',
                                }}
                            >
                                <Attributes
                                    asset={asset}
                                    displayControls={previewLocked}
                                    pinnedOnly={true}
                                />
                            </div>
                        )}
                    </Stack>
                </Paper>
            )}
        </Popper>
    );
}
