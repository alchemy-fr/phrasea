import {RefObject} from "react";

export const annotationZIndex = 100;

export type AssetAnnotationHandle = {
    render: () => void;
};

export type ZoomRef = RefObject<number | null>;

export function getZoomFromRef(zoomRef: ZoomRef): number {
    return zoomRef.current ?? 1;
}
