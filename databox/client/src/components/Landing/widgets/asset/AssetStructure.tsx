import {PropsWithChildren} from 'react';
import {Box, Theme} from '@mui/material';
import {SystemCssProperties} from '@mui/system';
import {videoPlayerSx} from '@alchemy/phrasea-framework';
import {NodeViewContent} from '@tiptap/react';
import {AssetWidgetProps} from './types.ts';

type Props = PropsWithChildren<AssetWidgetProps>;

export default function AssetStructure({
    gap,
    maxWidth,
    maxHeight,
    children,
}: Props) {
    return (
        <>
            <Box
                sx={() => ({
                    display: 'flex',
                    flexDirection: 'row',
                    gap,
                })}
            >
                <Box
                    sx={theme => ({
                        width: maxWidth,
                        height: maxHeight,
                        img: {
                            maxWidth: maxWidth,
                            maxHeight: maxHeight,
                        },
                        ...(videoPlayerSx(theme) as SystemCssProperties<Theme>),
                    })}
                >
                    {children}
                </Box>
                <NodeViewContent
                    style={{
                        flex: 1,
                        border: '1px solid',
                    }}
                />
            </Box>
        </>
    );
}
