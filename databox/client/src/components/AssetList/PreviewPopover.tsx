import {useCallback, useContext, useState} from 'react';
import {Asset} from '../../types.ts';
import {Paper, Popper, Stack} from '@mui/material';
import FilePlayer from '../Media/Asset/FilePlayer.tsx';
import {getRelativeViewHeight, getRelativeViewWidth} from '../../lib/style.ts';
import {createDimensions} from '../Media/Asset/Players';
import {zIndex} from '../../themes/zIndex.ts';
import Attributes, {attributesSx} from '../Media/Asset/Attribute/Attributes.tsx';
import {DisplayContext} from "../Media/DisplayContext.tsx";

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
                        ...attributesSx(),
                    }}
                >
                    <Stack direction={'row'} spacing={1}>
                        <div>
                            <FilePlayer
                                key={asset.id}
                                file={asset.preview!.file!}
                                dimensions={createDimensions(
                                    width / 2,
                                    height
                                )}
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
