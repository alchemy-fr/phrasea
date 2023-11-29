import {createContext} from 'react';

export type TDrawerContext = {
    closeDrawer: () => void;
};

const DrawerContext = createContext<TDrawerContext | undefined>(undefined);

export default DrawerContext;
