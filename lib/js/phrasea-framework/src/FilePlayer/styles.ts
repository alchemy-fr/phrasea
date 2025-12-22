import {Theme} from '@mui/material/styles';
import {SxProps} from '@mui/material';
import {FilePlayerClasses} from './types';

export function videoPlayerSx(theme: Theme): SxProps {
    return {
        [`.${FilePlayerClasses.VideoPlayer}`]: {
            position: 'relative',
            backgroundColor: theme.palette.common.black,
            display: 'flex',
            justifyContent: 'center',
            alignItems: 'center',
            [`&.${FilePlayerClasses.IsAudio}`]: {
                backgroundColor: theme.palette.background.default,
            },
            [`.${FilePlayerClasses.Controls}`]: {
                'pointerEvents': 'none',
                'display': 'flex',
                'flexDirection': 'column',
                'justifyContent': 'center',
                'alignItems': 'center',
                'position': 'absolute',
                'top': 0,
                'left': 0,
                'right': 0,
                'bottom': 0,
                'zIndex': 1,
                '.MuiButtonBase-root': {
                    'transition': theme.transitions.create(['opacity'], {
                        duration: 100,
                    }),
                    'pointerEvents': 'auto',
                    'bgcolor': 'primary.contrastText',
                    '&:hover': {
                        bgcolor: 'primary.contrastText',
                    },
                },
            },

            [`&.${FilePlayerClasses.Playing}`]: {
                [`.${FilePlayerClasses.Controls}`]: {
                    opacity: 0,
                },
                '&:hover': {
                    [`.${FilePlayerClasses.Controls}`]: {
                        opacity: 1,
                    },
                },
            },
        },
    };
}
