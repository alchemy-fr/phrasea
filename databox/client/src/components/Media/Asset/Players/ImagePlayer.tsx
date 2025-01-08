import AssetAnnotationsOverlay, {AssetAnnotationHandle} from "../Annotations/AssetAnnotationsOverlay.tsx";
import {File} from "../../../../types.ts";
import {PlayerProps} from "./index.ts";
import {AssetAnnotation} from "../Annotations/annotationTypes.ts";
import AnnotateWrapper from "../Annotations/AnnotateWrapper.tsx";
import React, {useRef} from "react";

type Props = {
    file: File;
    title?: string;
    annotations?: AssetAnnotation[] | undefined;
} & PlayerProps;

export default function ImagePlayer({file, title, annotations, onLoad, onNewAnnotation}: Props) {
    const annotationsOverlayRef = useRef<AssetAnnotationHandle | null>(null);
    const isSvg = file.type === 'image/svg+xml';

    const pOnLoad = React.useCallback(() => {
        onLoad?.();
        annotationsOverlayRef.current?.render();
    }, [onLoad]);

    React.useEffect(() => {
        annotationsOverlayRef.current?.render();
    }, [file]);

    return <>
        <AnnotateWrapper
            onNewAnnotation={onNewAnnotation}
        >
            {annotations ? (
                <AssetAnnotationsOverlay
                    ref={annotationsOverlayRef}
                    annotations={annotations}
                />
            ) : null}
            <img
            style={{
                maxWidth: '100%',
                maxHeight: '100%',
                display: 'block',
                ...(isSvg ? {width: '100%'} : {}),
            }}
            crossOrigin="anonymous"
            src={file.url}
            alt={title}
            onLoad={pOnLoad}
        />
        </AnnotateWrapper>
    </>
}
