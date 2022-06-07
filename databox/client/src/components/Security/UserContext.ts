import React from "react";
import {User} from "../../types";
import {ThemeName} from "../../lib/theme";

export type TUserContext = {
    user?: User | undefined;
    logout?: () => void | undefined;
    changeTheme?: (name: ThemeName) => void;
    currentTheme?: ThemeName;
}

export const UserContext = React.createContext<TUserContext>({});
