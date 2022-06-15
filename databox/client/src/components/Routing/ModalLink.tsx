import React from 'react';
import {Link, LinkProps, useLocation} from "react-router-dom";
import {getPath} from "../../routes";

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
