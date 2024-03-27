import {DOMAttributes, MouseEventHandler, PropsWithChildren} from 'react';
import {Box, SxProps} from '@mui/material';
import {alpha, Theme} from '@mui/material/styles';
import assetClasses from "../../AssetList/classes.ts";
import {createThumbActiveStyle} from "./AssetThumb.tsx";

type Props = PropsWithChildren<
    {
        size: number;
        selected?: boolean;
        onMouseOver?: MouseEventHandler | undefined;
        className?: string | undefined;
    } & DOMAttributes<HTMLElement>
>;

export function createSizeTransition(theme: Theme) {
    return theme.transitions.create(['height', 'width'], {duration: 300});
}

export const thumbSx = (thumbSize: number, theme: Theme, overridden: SxProps = {}) => ({
    [`.${assetClasses.thumbWrapper}`]: {
        display: 'flex',
        overflow: 'hidden',
        alignItems: 'center',
        position: 'relative',
        justifyContent: 'center',
        backgroundColor: theme.palette.grey[100],
        img: {
            maxWidth: '100%',
            maxHeight: '100%',
        },
        width: thumbSize,
        height: thumbSize,
        transition: createSizeTransition(theme),
        '> div': {
            display: 'contents',
        },
        ...createThumbActiveStyle(),
        ...overridden,
    },
});

export default function Thumb({
    selected,
    children,
    onMouseOver,
    onMouseLeave,
    className,
}: Props) {
    return (
        <Box
            onMouseOver={onMouseOver}
            onMouseLeave={onMouseLeave}
            className={className}
        >
            {selected && (
                <Box
                    sx={theme => ({
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        bottom: 0,
                        right: 0,
                        backgroundColor: alpha(theme.palette.primary.main, 0.3),
                        zIndex: 1,
                    })}
                />
            )}
            {children}
        </Box>
    );
}
