import React, {forwardRef} from 'react';
import ToggleButton, {ToggleButtonProps} from '@mui/material/ToggleButton';
import Tooltip, {TooltipProps} from '@mui/material/Tooltip';

type TooltipToggleButtonProps = ToggleButtonProps & {
    tooltipProps: Omit<TooltipProps, 'children'>;
};

const TooltipToggleButton = forwardRef<HTMLButtonElement, TooltipToggleButtonProps>(
    ({tooltipProps, ...props}, ref) => {
        return (
            <Tooltip {...tooltipProps}>
                <ToggleButton ref={ref} {...props} />
            </Tooltip>
        );
    }
);

export default TooltipToggleButton;
