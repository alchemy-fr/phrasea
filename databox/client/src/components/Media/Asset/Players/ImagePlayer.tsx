import {File} from '../../../../types.ts';
import {PlayerProps} from './index.ts';
import {AssetAnnotation} from '../Annotations/annotationTypes.ts';
import React, {useRef} from 'react';
import FileToolbar from './FileToolbar.tsx';
import {AssetAnnotationHandle} from '../Annotations/AnnotateWrapper.tsx';

type Props = {
    file: File;
    title?: string;
    annotations?: AssetAnnotation[] | undefined;
} & PlayerProps;

export default function ImagePlayer({
    file,
    title,
    annotations,
    onLoad,
    annotationsControl,
    zoomEnabled,
    controls,
}: Props) {
    const annotationsOverlayRef = useRef<AssetAnnotationHandle | null>(null);
    const isSvg = file.type === 'image/svg+xml';

    const pOnLoad = React.useCallback(() => {
        onLoad?.();
        annotationsOverlayRef.current?.render();
    }, [onLoad]);

    React.useEffect(() => {
        annotationsOverlayRef.current?.render();
    }, [file]);

    return (
        <>
            <FileToolbar
                key={file.id}
                controls={controls}
                annotationsControl={annotationsControl}
                annotations={annotations}
                zoomEnabled={zoomEnabled}
                annotationEnabled={true}
                forceHand={true}
            >
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
            </FileToolbar>
        </>
    );
}
