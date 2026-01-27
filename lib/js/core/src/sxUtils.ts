import {SxProps, Theme} from '@mui/material';
import {SystemStyleObject} from '@mui/system/styleFunctionSx/styleFunctionSx';

export function resolveSx<T extends Theme>(
    sx: SxProps<Theme> | undefined,
    theme: T
): SystemStyleObject<Theme> {
    return typeof sx === 'function' ? sx(theme) : sx as SystemStyleObject<Theme> || {};
}

export function sumSpacing(theme: Theme, spacing: number, addedValue: number): string {
    return `${(addedValue + parseInt(theme.spacing(spacing)))}px`;
}
