import React, {useContext, useRef} from 'react';
import {Asset} from '../../types';
import {DisplayContext} from '../Media/DisplayContext';
import {OnPreviewToggle} from './types';

export function usePreview(resettingValues: any[]) {
    const displayContext = useContext(DisplayContext);
    const d = displayContext!.state;
    const previewTimer = useRef<ReturnType<typeof setTimeout>>();
    const [previewAnchorEl, setPreviewAnchorEl] = React.useState<null | {
        asset: Asset;
        anchorEl: HTMLElement;
    }>(null);
    const previewEnterDelay = 50;
    const previewLeaveDelay = 100;

    React.useEffect(() => {
        // Force preview close on result change
        setPreviewAnchorEl(null);
    }, resettingValues);

    const onPreviewToggle = React.useCallback<OnPreviewToggle>(
        ({asset, display, anchorEl, lock}): void => {
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

            if (!d.displayPreview) {
                return;
            }

            setPreviewAnchorEl(p => {
                const deferred = !p || d.previewLocked;

                if (!anchorEl) {
                    return p;
                }

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

            if (undefined !== lock) {
                displayContext?.setState(p => ({
                    ...p,
                    previewLocked: lock,
                }));
            }

            // eslint-disable-next-line
        },
        [setPreviewAnchorEl, d]
    );

    const onPreviewHide = React.useCallback((): void => {
        if (previewTimer.current) {
            clearTimeout(previewTimer.current);
        }
        setPreviewAnchorEl(null);
    }, [setPreviewAnchorEl, d]);

    return {
        previewAnchorEl,
        onPreviewToggle,
        onPreviewHide,
    };
}
