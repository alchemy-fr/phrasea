import {ApiFile} from '../../../../types.ts';
import {PlayerProps} from './index.ts';
import {
    AssetAnnotation,
    AnnotationsControl,
} from '../Annotations/annotationTypes.ts';
import React, {useRef} from 'react';
import FileToolbar from './FileToolbar.tsx';

type Props = {
    file: ApiFile;
    title?: string;
    annotations?: AssetAnnotation[] | undefined;
} & PlayerProps;

export default function ImagePlayer({
    file,
    title,
    onLoad,
    controls,
    ...playerProps
}: Props) {
    const annotationsOverlayRef = useRef<AnnotationsControl | null>(null);
    const isSvg = file.type === 'image/svg+xml';

    const pOnLoad = React.useCallback(() => {
        onLoad?.();
        annotationsOverlayRef.current?.render();
    }, [onLoad]);

    React.useEffect(() => {
        annotationsOverlayRef.current?.render();
    }, [file]);

    const img = (
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
    );

    if (
        !playerProps.assetAnnotationsRef &&
        !playerProps.annotations &&
        !playerProps.zoomEnabled
    ) {
        return img;
    }

    return (
        <>
            <FileToolbar
                {...playerProps}
                key={file.id}
                controls={controls}
                annotationEnabled={true}
                forceHand={true}
            >
                {img}
            </FileToolbar>
        </>
    );
}
