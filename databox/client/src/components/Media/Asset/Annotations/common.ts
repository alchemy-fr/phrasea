import {RefObject} from "react";
import {AssetAnnotation} from "./annotationTypes.ts";
import {AnnotationResizeHandler, OnResizeEvent} from "./events.ts";

export const annotationZIndex = 100;

export type AssetAnnotationHandle = {
    render: () => void;
};

export type ZoomRef = RefObject<number | null>;

export function getZoomFromRef(zoomRef: ZoomRef): number {
    return zoomRef.current ?? 1;
}

export type ShapeControlRef = RefObject<HTMLDivElement | null>;

export const getStandardMoveHandler = (): AnnotationResizeHandler => ({
    annotation,
    deltaX,
    deltaY,
    relativeY,
    relativeX,
}: OnResizeEvent): AssetAnnotation => {
    return {
        ...annotation,
        x: (annotation.x || 0) + relativeX(deltaX),
        y: (annotation.y || 0) + relativeY(deltaY),
    }
};
