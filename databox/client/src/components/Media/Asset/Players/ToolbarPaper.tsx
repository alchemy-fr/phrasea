import {Paper, PaperProps} from '@mui/material';
import {PropsWithChildren} from 'react';
import {annotationZIndex} from '../Annotations/AnnotateWrapper.tsx';

type Props = PropsWithChildren<{
    annotationActive: boolean;
    sx?: PaperProps['sx'];
}>;

export default function ToolbarPaper({children, annotationActive, sx}: Props) {
    return (
        <Paper
            // @ts-expect-error MUI types are wrong
            sx={theme => ({
                borderRadius: theme.shape.borderRadius,
                position: 'absolute',
                zIndex: annotationZIndex + 1,
                backgroundColor: `rgba(255, 255, 255, 0.8)`,
                alignItems: 'center',
                ...(annotationActive
                    ? {
                          pointerEvents: 'none',
                          opacity: 0.6,
                      }
                    : {}),
                left: '50%',
                transform: 'translateX(-50%)',
                p: 2,
                ...(typeof sx === 'function' ? sx(theme) : sx),
            })}
        >
            {children}
        </Paper>
    );
}
