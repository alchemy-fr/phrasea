import {AnnotationType, AssetAnnotation} from '../../../../types.ts';
import PointAnnotation from './PointAnnotation.tsx';
import React, {FC} from 'react';
import RectAnnotation from './RectAnnotation.tsx';
import CircleAnnotation from './CircleAnnotation.tsx';

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
    };

    return (
        <div
            style={{
                position: 'absolute',
                overflow: 'hidden',
                zIndex: annotationZIndex,
                width: '100%',
                height: '100%',
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
