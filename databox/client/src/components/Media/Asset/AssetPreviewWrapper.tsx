import React, {PropsWithChildren, useEffect} from "react";
import {Asset} from "../../../types";
import Attributes from "./Attribute/Attributes";
import {Popover} from "@mui/material";

type Props = PropsWithChildren<{
    displayAttributes: boolean;
    asset: Asset;
}>;

export default function AssetPreviewWrapper({
                                                asset,
                                                children,
                                                displayAttributes,
                                            }: Props): JSX.Element {
    const [anchorEl, setAnchorEl] = React.useState<HTMLElement | null>(null);
    const timeout = React.useRef<ReturnType<typeof setTimeout> | undefined>();

    const handlePopoverOpen = (event: React.MouseEvent<HTMLElement, MouseEvent>) => {
        const t = event.currentTarget;
        timeout.current = setTimeout(() => {
            setAnchorEl(t);
        }, 500);
    };

    const handlePopoverClose = () => {
        if (timeout.current) {
            clearTimeout(timeout.current);
        }
        setAnchorEl(null);
    };

    useEffect(() => () => timeout.current && clearTimeout(timeout.current), []);

    const open = Boolean(anchorEl);

    if (!asset.preview) {
        return <>{children}</>
    }

    return <>
        <div
            aria-owns={open ? 'mouse-over-popover' : undefined}
            aria-haspopup="true"
            onMouseEnter={handlePopoverOpen}
            onMouseLeave={handlePopoverClose}
            onMouseDown={handlePopoverClose}
        >
            {children}
        </div>
        <Popover
            id="mouse-over-popover"
            sx={(theme) => ({
                pointerEvents: 'none',
                'paper': {
                    padding: theme.spacing(1),
                }
            })}
            open={open}
            anchorEl={anchorEl}
            anchorOrigin={{
                vertical: 'bottom',
                horizontal: 'left',
            }}
            transformOrigin={{
                vertical: 'top',
                horizontal: 'left',
            }}
            onClose={handlePopoverClose}
            disableRestoreFocus
        >
            <div className={'asset-preview'}>
                <img src={asset.preview.url}
                     style={{
                         maxWidth: 400,
                         maxHeight: 400,
                     }}
                     alt="Preview"/>
                {displayAttributes && <div>
                    <Attributes
                        asset={asset}
                    />
                </div>}
            </div>
        </Popover>
    </>
}
