import {Theme} from '@mui/material';

export function getContrastText(
    theme: Theme,
    color: string | undefined,
): string {
    try {
        return theme.palette.getContrastText(color || '#FFFFFF');
    } catch (e) {
        return '#000000'; // Fallback to black if there's an error
    }
}
