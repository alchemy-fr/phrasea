import {Theme} from '@mui/material';

export function resolveSx<T extends Theme, Style>(
    sx: Style | undefined,
    theme: T
): Style {
    return typeof sx === 'function' ? sx(theme) : (sx ?? {} as Style);
}

export function sumSpacing(theme: Theme, spacing: number, addedValue: number): string {
    return `${(addedValue + parseInt(theme.spacing(spacing)))}px`;
}
