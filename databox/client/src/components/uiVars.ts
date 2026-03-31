import {Theme} from '@mui/material';
import {SystemStyleObject} from '@mui/system/styleFunctionSx/styleFunctionSx';

export const leftPanelWidth = 360;
export function getMediaBackgroundColor<T extends Theme>(
    theme: T
): SystemStyleObject<T> {
    return {
        backgroundColor: theme.palette.grey[200],
        ...theme.applyStyles('dark', {
            backgroundColor: theme.palette.grey[700],
        }),
    };
}
export const scrollbarWidth = 8;
