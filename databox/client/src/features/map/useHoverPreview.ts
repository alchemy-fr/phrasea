import {MouseEvent, useCallback, useEffect, useRef} from 'react';
import type {Asset} from '@/types/api';
import {usePreview} from '@/features/assets/list/preview/PreviewProvider';

/**
 * Handlers opening the hover preview of the grid on an element of the map.
 * The element can go away under the pointer (popup closed, markers of a new
 * zoom level) without a `mouseleave`: the preview is then closed too.
 */
export function useHoverPreview(asset: Asset | undefined) {
    const preview = usePreview();
    const latest = useRef({preview, asset});
    useEffect(() => {
        latest.current = {preview, asset};
    });
    const hovered = useRef(false);

    const onMouseLeave = useCallback(() => {
        const {preview, asset} = latest.current;
        if (hovered.current && asset) {
            hovered.current = false;
            preview.onLeave(asset);
        }
    }, []);
    // Stable: the preview context changes as the preview opens, which must
    // not count as leaving
    useEffect(() => onMouseLeave, [onMouseLeave]);

    const onMouseEnter = useCallback((e: MouseEvent<HTMLElement>) => {
        const {preview, asset} = latest.current;
        if (asset) {
            hovered.current = true;
            preview.onEnter(asset, e.currentTarget);
        }
    }, []);

    return {onMouseEnter, onMouseLeave};
}
