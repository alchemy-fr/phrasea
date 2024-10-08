import {Theme, ThemeOptions} from '@mui/material';

const baseTheme: ThemeOptions = {
    typography: {
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
        common: {
            white: '#FFF',
        },
    },
};

export default baseTheme;

export const leftPanelWidth = 360;

export function getMediaBackgroundColor(theme: Theme): string {
    return theme.palette.grey[200];
}
