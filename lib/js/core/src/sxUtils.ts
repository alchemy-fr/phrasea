import {Theme} from '@mui/material';

export function resolveSx<T extends Theme, Style>(
    sx: Style,
    theme: T
): Style {
    return typeof sx === 'function' ? sx(theme) : sx;
}
