import {PropsWithChildren} from 'react';
import {Box, Theme} from '@mui/material';
import {SystemCssProperties} from '@mui/system';
import {videoPlayerSx} from '@alchemy/phrasea-framework';

type Props = PropsWithChildren<{
    size: number;
    gap: number;
    backgroundColor?: string;
}>;
export type {Props as GridStructureProps};

export enum GridClasses {
    Asset = 'grid-asset',
}

export default function GridStructure({
    size,
    gap,
    children,
    backgroundColor,
}: Props) {
    return (
        <>
            <Box
                sx={theme => ({
                    display: 'flex',
                    flexDirection: 'row',
                    flexWrap: 'wrap',
                    gap,
                    ...(videoPlayerSx(theme) as SystemCssProperties<Theme>),
                    img: {
                        maxWidth: '100%',
                        maxHeight: '100%',
                    },
                    [`.${GridClasses.Asset}`]: {
                        backgroundColor,
                        display: 'flex',
                        justifyContent: 'center',
                        alignItems: 'center',
                        width: size,
                        height: size,
                        overflow: 'hidden',
                    },
                })}
            >
                {children}
            </Box>
        </>
    );
}
