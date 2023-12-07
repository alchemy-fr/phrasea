import React from "react";
import AuthenticationContext, {TAuthContext} from "../context/AuthenticationContext";

export function useAuth(): TAuthContext {
    return React.useContext(AuthenticationContext);
}
