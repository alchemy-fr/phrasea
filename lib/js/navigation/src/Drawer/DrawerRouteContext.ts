import {createContext} from 'react';
import {RouteParameters} from "../types";

export type TDrawerRouteContext = {
    path: string;
    params: RouteParameters;
};

export default createContext<TDrawerRouteContext | undefined>(undefined);
