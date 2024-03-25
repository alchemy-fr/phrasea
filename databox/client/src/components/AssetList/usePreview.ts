import React, {useContext, useRef} from "react";
import {Asset} from "../../types.ts";
import {DisplayContext} from "../Media/DisplayContext.tsx";
import {OnPreviewToggle} from "./types.ts";

export function usePreview(resettingValues: any[]) {
    const d = useContext(DisplayContext)!;
    const previewTimer = useRef<ReturnType<typeof setTimeout>>();
    const [previewAnchorEl, setPreviewAnchorEl] = React.useState<null | {
        asset: Asset;
        anchorEl: HTMLElement;
    }>(null);
    const previewEnterDelay = 500;
    const previewLeaveDelay = 400;

    React.useEffect(() => {
        // Force preview close on result change
        setPreviewAnchorEl(null);
    }, resettingValues);

    const onPreviewToggle = React.useCallback<OnPreviewToggle>(
        (asset, display, anchorEl): void => {
            if (!asset.preview?.file || !d.displayPreview) {
                return;
            }
            if (previewTimer.current) {
                clearTimeout(previewTimer.current);
            }
            if (!display) {
                if (!d.previewLocked) {
                    previewTimer.current = setTimeout(() => {
                        setPreviewAnchorEl(null);
                    }, previewLeaveDelay);
                }
                return;
            }

            setPreviewAnchorEl(p => {
                const deferred = !p || d.previewLocked;

                if (!deferred) {
                    return {
                        asset,
                        anchorEl,
                    };
                }

                previewTimer.current = setTimeout(() => {
                    setPreviewAnchorEl({
                        asset,
                        anchorEl,
                    });
                }, previewEnterDelay);

                return p;
            });
            // eslint-disable-next-line
        },
        [setPreviewAnchorEl, d]
    );

    return {
        previewAnchorEl,
        onPreviewToggle,
    };
}
