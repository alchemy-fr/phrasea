import {PropsWithChildren, useState} from "react";
import {Tooltip, TooltipProps} from "@mui/material";

type Props = PropsWithChildren<TooltipProps>;

export default function FastTooltip({
    children,
    ...rest
}: Props) {
    const [renderTooltip, setRenderTooltip] = useState(false);

    return (
        <div
            onMouseEnter={() => !renderTooltip && setRenderTooltip(true)}
            style={{display: 'contents'}}
        >
            {renderTooltip ? <Tooltip
                {...rest}>
                {children}
            </Tooltip> : children}
        </div>
    );
}
