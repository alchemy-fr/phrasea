import {LinkProps} from 'react-router-dom';
import React, {MouseEventHandler} from 'react';
import {
    NavigateToOverlayFunction,
    RouteDefinition,
    RouteParameters,
    useNavigateToOverlay,
} from '@alchemy/navigation';

type Props = {
    route: RouteDefinition;
    params?: RouteParameters;
} & Omit<LinkProps, 'to'>;

export default React.forwardRef<HTMLAnchorElement, Props>(
    ({route, params, onClick, ...rest}, ref) => {
        const navigateToModal = useNavigateToModal();

        const clickHandler: MouseEventHandler<HTMLAnchorElement> = e => {
            onClick && onClick(e);

            navigateToModal(route, params);
        };

        return <a ref={ref} onClick={clickHandler} {...rest} />;
    }
);

export function useNavigateToModal(): NavigateToOverlayFunction {
    return useNavigateToOverlay('_m');
}
