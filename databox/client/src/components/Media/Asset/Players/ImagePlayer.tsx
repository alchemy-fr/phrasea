import AssetAnnotationsOverlay from "../Annotations/AssetAnnotationsOverlay.tsx";
import {AssetAnnotation, File} from "../../../../types.ts";
import {PlayerProps} from "./index.ts";

type Props = {
    file: File;
    title?: string;
    annotations?: AssetAnnotation[] | undefined;
} & PlayerProps;

export default function ImagePlayer({file, title, annotations, onLoad}: Props) {

    const isSvg = file.type === 'image/svg+xml';

    return <>
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
    </>
}
