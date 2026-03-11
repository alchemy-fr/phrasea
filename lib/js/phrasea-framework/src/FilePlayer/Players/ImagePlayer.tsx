
import React from 'react';
import {FilePlayerProps} from '../types';

type Props = FilePlayerProps;

export default function ImagePlayer({file, title, onLoad, cover}: Props) {
    const isSvg = file.type === 'image/svg+xml';

    return (
        <img
            style={{
                maxWidth: '100%',
                display: 'block',
                ...(isSvg ? {width: '100%'} : {}),
                ...(cover
                    ? {
                          width: '100%',
                          height: '100%',
                          objectFit: 'cover',
                      }
                    : {}),
            }}
            crossOrigin="anonymous"
            src={file.url}
            alt={title}
            onLoad={onLoad}
        />
    );
}
