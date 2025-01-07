import AssetAnnotationsOverlay from "../Annotations/AssetAnnotationsOverlay.tsx";
import {File} from "../../../../types.ts";
import {PlayerProps} from "./index.ts";
import {AssetAnnotation} from "../Annotations/annotationTypes.ts";
import AnnotateTool from "../Annotations/AnnotateTool.tsx";

type Props = {
    file: File;
    title?: string;
    annotations?: AssetAnnotation[] | undefined;
} & PlayerProps;

export default function ImagePlayer({file, title, annotations, onLoad, onNewAnnotation}: Props) {

    const isSvg = file.type === 'image/svg+xml';

    return <>
        <AnnotateTool
            onNewAnnotation={onNewAnnotation}
        >
            {annotations ? (
                <AssetAnnotationsOverlay
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
            onLoad={onLoad}
        />
        </AnnotateTool>
    </>
}
