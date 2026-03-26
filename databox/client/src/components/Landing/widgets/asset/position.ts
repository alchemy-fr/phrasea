import {Position} from './types.ts';
import {FlexboxProps} from '@mui/system';

export function getFlexDirection(
    position: Position
): FlexboxProps['flexDirection'] {
    switch (position) {
        case 'top':
            return 'column';
        case 'bottom':
            return 'column-reverse';
        case 'left':
            return 'row';
        case 'right':
            return 'row-reverse';
    }
}
