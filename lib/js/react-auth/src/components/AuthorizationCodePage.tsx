import {useNavigate} from "react-router-dom";
import {toast} from "react-toastify";
import React from "react";
import {useAuthorizationCode, UseAuthorizationCodeProps} from "../hooks/useAuthorizationCode";

type Props = Omit<UseAuthorizationCodeProps, "navigate">;

export default function AuthorizationCodePage(props: Props) {
    const navigate = useNavigate();
    const {
        error
    } = useAuthorizationCode({
        navigate: (path, options) => navigate(path, options),
        ...props,
    });

    React.useEffect(() => {
        if (error) {
            console.error(error);
            toast.warn(error.toString());
        }
    }, [error]);

    if (error) {
        throw error;
    }

    return <></>
}
