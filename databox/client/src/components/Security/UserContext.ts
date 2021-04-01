import React from "react";
import {User} from "../../types";

export type TUserContext = {
    user?: User;
}

export const UserContext = React.createContext<TUserContext>({
    user: undefined,
});
