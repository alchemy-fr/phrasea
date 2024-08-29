import React, {HTMLProps, MouseEventHandler} from 'react';
import {
    CloseOverlayFunction,
    NavigateToOverlayFunction,
    RouteDefinition,
    RouteParameters,
    useCloseOverlay,
    useNavigateToOverlay,
} from '@alchemy/navigation';
import { useTranslation } from 'react-i18next';

type Props = {
    route: RouteDefinition;
    params?: RouteParameters;
} & HTMLProps<HTMLAnchorElement>;

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

export function useCloseModal(): CloseOverlayFunction {
    return useCloseOverlay('_m');
}
