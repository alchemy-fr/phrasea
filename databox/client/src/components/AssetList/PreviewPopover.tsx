import {useCallback, useContext, useState} from 'react';
import {Asset} from '../../types';
import {Paper, Popper, Stack} from '@mui/material';
import FilePlayer from '../Media/Asset/FilePlayer';
import {getRelativeViewHeight, getRelativeViewWidth} from '../../lib/style';
import {createDimensions} from '../Media/Asset/Players';
import {zIndex} from '../../themes/zIndex';
import Attributes, {
    attributesSx,
} from '../Media/Asset/Attribute/Attributes';
import {DisplayContext} from '../Media/DisplayContext';

type Props = {
    anchorEl: HTMLElement | undefined;
    asset: Asset | undefined;
    displayAttributes: boolean;
};

const relativeSize = 50;

export default function PreviewPopover({
    asset,
    anchorEl,
    displayAttributes,
}: Props) {
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
                            maxHeight: `calc(${height}px - ${theme.spacing(2)})`,
                        })}
                    >
                        <div style={{
                            maxHeight: height,
                        }}>
                            <FilePlayer
                                key={asset.id}
                                file={asset.preview!.file!}
                                dimensions={createDimensions(width / 2, height)}
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
