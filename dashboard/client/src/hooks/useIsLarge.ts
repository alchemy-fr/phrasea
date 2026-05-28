import {useMediaQuery, useTheme} from '@mui/material';

export function useIsLarge() {
    const theme = useTheme();
    return useMediaQuery(theme.breakpoints.up('sm'));
}
