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

    console.log('sortedBreakpoints', sortedBreakpoints);

    for (const [key, value] of sortedBreakpoints) {
        console.log('key', key, value);
        if (innerWidth >= value && cases[key] !== undefined) {
            console.log('x', innerWidth);
            return cases[key];
        }
    }

    return undefined;
}
