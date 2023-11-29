import {useContext} from 'react';
import DrawerContext from '../contexts/DrawerContext';

export function useCloseDrawer() {
    const context = useContext(DrawerContext);

    if (!context) {
        throw new Error('9832'); // Not in a drawer context
    }

    return () => {
        context.closeDrawer();
    };
}
