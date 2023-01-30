import React from "react";
import {User} from "../../types";

export type TUserContext = {
    user?: User | undefined;
    logout?: () => void | undefined;
}

export const UserContext = React.createContext<TUserContext>({});
