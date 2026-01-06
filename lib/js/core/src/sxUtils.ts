import {Theme} from '@mui/material';

export function resolveSx<T extends Theme, Style>(
    sx: Style | undefined,
    theme: T
): Style {
    return typeof sx === 'function' ? sx(theme) : (sx ?? {} as Style);
}
