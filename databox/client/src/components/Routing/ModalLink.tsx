import React, {HTMLProps, MouseEventHandler} from 'react';
import {
    CloseOverlayFunction,
    NavigateToOverlayFunction,
    RouteDefinition,
    RouteParameters,
    useCloseOverlay,
    useNavigateToOverlay,
} from '@alchemy/navigation';
import {CloseWrapper} from '@alchemy/phrasea-ui';

type Props = {
    route: RouteDefinition;
    params?: RouteParameters;
    closeWrapper?: CloseWrapper;
} & HTMLProps<HTMLAnchorElement>;

export default React.forwardRef<HTMLAnchorElement, Props>(
    ({route, params, onClick, closeWrapper, ...rest}, ref) => {
        const navigateToModal = useNavigateToModal();

        const clickHandler: MouseEventHandler<HTMLAnchorElement> = e => {
            onClick?.(e);

            navigateToModal(route, params);
        };

        return (
            <a
                ref={ref}
                onClick={
                    closeWrapper ? closeWrapper(clickHandler) : clickHandler
                }
                {...rest}
            />
        );
    }
);

export function useNavigateToModal(): NavigateToOverlayFunction {
    return useNavigateToOverlay('_m');
}

export function useCloseModal(): CloseOverlayFunction {
    return useCloseOverlay('_m');
}
