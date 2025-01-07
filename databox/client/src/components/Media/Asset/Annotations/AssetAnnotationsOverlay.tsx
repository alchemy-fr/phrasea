import PointAnnotation from './PointAnnotation.tsx';
import React, {FC} from 'react';
import RectAnnotation from './RectAnnotation.tsx';
import CircleAnnotation from './CircleAnnotation.tsx';
import {AnnotationType, AssetAnnotation} from "./annotationTypes.ts";
import DrawAnnotation from "./DrawAnnotation.tsx";

type Props = {
    annotations: AssetAnnotation[];
};

export const annotationZIndex = 100;

export default function AssetAnnotationsOverlay({annotations}: Props) {
    const types: {
        [key in AnnotationType]?: FC<any>;
    } = {
        [AnnotationType.Point]: PointAnnotation,
        [AnnotationType.Rect]: RectAnnotation,
        [AnnotationType.Circle]: CircleAnnotation,
        [AnnotationType.Draw]: DrawAnnotation,
    };

    return (
        <div
            style={{
                position: 'absolute',
                overflow: 'hidden',
                zIndex: annotationZIndex,
                width: '100%',
                height: '100%',
                top: 0,
                left: 0,
            }}
        >
            {annotations.map(({type, ...props}, i) => {
                if (!types[type]) {
                    return '';
                }

                return React.createElement(types[type]!, {
                    key: i,
                    ...props,
                });
            })}
        </div>
    );
}
