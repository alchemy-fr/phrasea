import {Breakpoint} from '@mui/system';
import {Theme} from '@mui/material';
import {useWindowSize} from './useWindowSize';

export function useMatchWindowWidthBreakpoint<T>(
    theme: Theme,
    cases: Partial<Record<Breakpoint, T>>
): T | undefined {
    const breakpoints = theme.breakpoints.values;

    const windowSize = useWindowSize();
    const { innerWidth } = windowSize;

    const sortedBreakpoints = Object.entries(breakpoints).sort(
        (a, b) => b[1] - a[1]
    ) as [Breakpoint, number][];

    for (const [key, value] of sortedBreakpoints) {
        if (innerWidth >= value && cases[key] !== undefined) {
            return cases[key];
        }
    }

    return undefined;
}
