import {AnnotationType, AssetAnnotation} from '../../../../types.ts';
import PointAnnotation from './PointAnnotation.tsx';
import React, {FC} from 'react';
import RectAnnotation from './RectAnnotation.tsx';
import CircleAnnotation from './CircleAnnotation.tsx';

type Props = {
    annotations: AssetAnnotation[];
};

export default function AssetAnnotationsOverlay({annotations}: Props) {
    const types: {
        [key in AnnotationType]?: FC<any>;
    } = {
        [AnnotationType.Point]: PointAnnotation,
        [AnnotationType.Rect]: RectAnnotation,
        [AnnotationType.Circle]: CircleAnnotation,
    };

    console.log('annotations', annotations);

    return (
        <div
            style={{
                position: 'absolute',
                overflow: 'hidden',
                zIndex: 1000,
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
