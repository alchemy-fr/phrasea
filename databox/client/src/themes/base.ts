import {Theme, ThemeOptions} from '@mui/material';

const baseTheme: ThemeOptions = {
    typography: {
        allVariants: {
            fontFamily: '"Manrope", sans-serif',
        },
        body1: {
            fontSize: 14,
        },
        h1: {
            fontSize: 24,
            fontWeight: 600,
        },
        h2: {
            fontSize: 19,
            fontWeight: 600,
        },
    },
    palette: {
        background: {
            default: '#f7f7f8',
        },
        common: {
            white: '#FFF',
        },
    },
    components: {
        MuiButton: {
            styleOverrides: {
                root: {
                    textTransform: 'none',
                },
            },
        },
        MuiTab: {
            styleOverrides: {
                root: {
                    textTransform: 'none',
                },
            },
        },
    },
};

export default baseTheme;

export const leftPanelWidth = 360;

export function getMediaBackgroundColor(theme: Theme): string {
    return theme.palette.grey[200];
}
