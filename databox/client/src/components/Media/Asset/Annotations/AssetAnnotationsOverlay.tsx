import {AnnotationType, AssetAnnotation} from "../../../../types.ts";
import PointAnnotation from "./PointAnnotation.tsx";
import React from "react";
import RectAnnotation from "./RectAnnotation.tsx";

type Props = {
    annotations: AssetAnnotation[];
};

export default function AssetAnnotationsOverlay({annotations}: Props) {

    const types = {
       [AnnotationType.Point]: PointAnnotation,
       [AnnotationType.Rect]: RectAnnotation,
    }

    console.log('annotations', annotations);
    return <div style={{
        position: 'absolute',
        zIndex: 1000,
        width: '100%',
        height: '100%',
    }}>
        {annotations.map(({
            type,
            ...props
        }, i) => {
            if (!types[type]) {
                return '';
            }

            return React.createElement(types[type]!, {
                key: i,
                ...props,
            });
        })}
    </div>
}
