import React, {useState} from 'react';
import {Link, LinkProps, useLocation, useNavigate} from "react-router-dom";
import {getPath} from "../../routes";

export const useModalPath = () => {
    const location = useLocation();
    const navigate = useNavigate();

    return (routeName: string, params?: Record<string, any>) => {
        navigate(getPath(routeName, params), {
            state: {
                background: location,
            }
        });
    };
}


type Props = {
    routeName: string;
    params?: Record<string, any>;
} & Omit<LinkProps, "to">;

export default React.forwardRef<HTMLAnchorElement, Props>(({
                                     routeName,
                                     params,
                                     ...rest
                                 }, ref) => {
    const location = useLocation();

    return <Link
        ref={ref}
        {...rest}
        to={getPath('app_' + routeName, params)}
        state={{background: location}}
    />
});
