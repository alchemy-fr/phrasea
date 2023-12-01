import {Link, LinkProps, To, useLocation, useNavigate} from 'react-router-dom';
import {getPath} from '../../routes';
import {NavigateOptions} from 'react-router/lib/hooks';
import {Key, Path} from 'history';
import React from "react";

type Props = {
    routeName: string;
    params?: Record<string, any>;
} & Omit<LinkProps, 'to'>;

export type StateWithBackground = {
    background?: Location;
};

export default React.forwardRef<HTMLAnchorElement, Props>(
    ({routeName, params, ...rest}, ref) => {
        const location = useLocation() as ModalLocation;

        return (
            <Link
                ref={ref}
                {...rest}
                to={getPath('app_' + routeName, params)}
                state={createNewState(location, undefined)}
            />
        );
    }
);

type ModalLocation = {
    state?: StateWithBackground;
    key: Key;
} & Path;

export function useNavigateToModal(): (
    to: To,
    options?: NavigateOptions
) => void {
    const navigate = useNavigate();
    const location = useLocation() as ModalLocation;

    return (to: To, options?: NavigateOptions) => {
        navigate(to, {
            replace: options?.replace,
            state: createNewState(location, options?.state),
        });
    };
}

function createNewState(
    location: ModalLocation,
    state: StateWithBackground | undefined
): StateWithBackground {
    return {
        ...(state ?? {}),
        background: location.state?.background ?? location,
    } as StateWithBackground;
}
