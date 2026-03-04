import {PropsWithChildren} from 'react';
import {Box, Theme} from '@mui/material';
import {SystemCssProperties} from '@mui/system';
import {videoPlayerSx} from '@alchemy/phrasea-framework';
import {NodeViewContent} from '@tiptap/react';
import {AssetWidgetProps} from './types.ts';
import {getFlexDirection} from './position.ts';

type Props = PropsWithChildren<AssetWidgetProps>;

export default function AssetStructure({
    gap,
    maxWidth,
    maxHeight,
    borderRadius,
    imagePosition,
    children,
}: Props) {
    return (
        <>
            <Box
                sx={() => ({
                    display: 'flex',
                    flexDirection: getFlexDirection(imagePosition),
                    gap,
                })}
            >
                <Box
                    sx={theme => ({
                        maxWidth: maxWidth,
                        maxHeight: maxHeight,
                        img: {
                            maxWidth: maxWidth,
                            maxHeight: maxHeight,
                            borderRadius,
                        },
                        ...(videoPlayerSx(theme) as SystemCssProperties<Theme>),
                    })}
                >
                    {children}
                </Box>
                <div
                    style={{
                        flexGrow: 1,
                    }}
                >
                    <NodeViewContent />
                </div>
            </Box>
        </>
    );
}
